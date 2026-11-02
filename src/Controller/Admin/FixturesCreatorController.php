<?php

declare(strict_types=1);

namespace PrestaShop\Module\PsFixturesCreator\Controller\Admin;

use Category;
use Context;
use Language;
use PrestaShop\Module\PsFixturesCreator\Generator\GenerationContext;
use PrestaShop\Module\PsFixturesCreator\Generator\GenerationRunner;
use PrestaShop\Module\PsFixturesCreator\Generator\GeneratorRegistry;
use PrestaShop\Module\PsFixturesCreator\Preset\PresetRegistry;
use PrestaShop\Module\PsFixturesCreator\Statistics\RunLogger;
use PrestaShop\Module\PsFixturesCreator\Statistics\StatisticsProvider;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Voter\PageVoter;
use Search;
use Shop;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Back office page of the module: a dashboard of the current data volume and a form to run generators.
 * Generation is executed in small HTTP batches driven by JavaScript, so it survives PHP time limits.
 */
class FixturesCreatorController extends FrameworkBundleAdminController
{
    public const LEGACY_CONTROLLER = 'AdminPsFixturesCreator';

    /** Hard cap for a single HTTP batch, whatever the client asks */
    private const MAX_BATCH = 2000;

    private GeneratorRegistry $registry;
    private GenerationRunner $runner;
    private StatisticsProvider $statisticsProvider;
    private RunLogger $runLogger;
    private PresetRegistry $presets;

    public function __construct(
        GeneratorRegistry $registry,
        GenerationRunner $runner,
        StatisticsProvider $statisticsProvider,
        RunLogger $runLogger,
        PresetRegistry $presets
    ) {
        $this->registry = $registry;
        $this->runner = $runner;
        $this->statisticsProvider = $statisticsProvider;
        $this->runLogger = $runLogger;
        $this->presets = $presets;
    }

    public function indexAction(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PageVoter::READ, self::LEGACY_CONTROLLER);

        $context = Context::getContext();
        $shops = array_map(static function (array $shop): array {
            return [
                'id' => (int) $shop['id_shop'],
                'groupId' => (int) $shop['id_shop_group'],
                'name' => (string) $shop['name'],
            ];
        }, array_values(Shop::getShops(false)));
        $languages = array_map(static function (array $lang): array {
            return ['id' => (int) $lang['id_lang'], 'name' => (string) $lang['name']];
        }, Language::getLanguages(false));

        return $this->render('@Modules/ps_fixturescreator/views/templates/admin/index.html.twig', [
            'layoutTitle' => 'Fixtures Creator',
            'enableSidebar' => false,
            'pageData' => [
                'generators' => $this->registry->describe(),
                'presets' => $this->presets->all(),
                'stats' => $this->buildStats(),
                'shops' => $shops,
                'languages' => $languages,
                'defaultShopId' => (int) $context->shop->id,
                'defaultLangId' => (int) $context->language->id,
                'canGenerate' => $this->isGranted(PageVoter::UPDATE, self::LEGACY_CONTROLLER),
                'environment' => [
                    'phpVersion' => PHP_VERSION,
                    'prestashopVersion' => _PS_VERSION_,
                    'maxExecutionTime' => (int) ini_get('max_execution_time'),
                    'memoryLimit' => (string) ini_get('memory_limit'),
                    'gd' => function_exists('imagecreatetruecolor'),
                    'debug' => (bool) _PS_MODE_DEV_,
                ],
                'urls' => [
                    'run' => $this->generateUrl('ps_fixturescreator_run'),
                    'stats' => $this->generateUrl('ps_fixturescreator_stats'),
                    'maintenance' => $this->generateUrl('ps_fixturescreator_maintenance'),
                ],
            ],
        ]);
    }

    public function runAction(Request $request): JsonResponse
    {
        if (!$this->isGranted(PageVoter::UPDATE, self::LEGACY_CONTROLLER)) {
            return new JsonResponse(['success' => false, 'error' => 'You do not have permission to generate fixtures.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->getPayload($request);
        $generatorName = (string) ($payload['generator'] ?? '');
        if (!$this->registry->has($generatorName)) {
            return new JsonResponse(['success' => false, 'error' => sprintf('Unknown generator "%s".', $generatorName)], Response::HTTP_BAD_REQUEST);
        }

        $count = max(0, min((int) ($payload['count'] ?? 0), self::MAX_BATCH));
        $options = is_array($payload['options'] ?? null) ? $payload['options'] : [];
        $generationContext = $this->buildGenerationContext($payload);

        try {
            $run = $this->runner->run($generatorName, $count, $options, $generationContext, 'ui');
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => sprintf('%s: %s', get_class($e), $e->getMessage()),
                'trace' => _PS_MODE_DEV_ ? $e->getTraceAsString() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'generator' => $generatorName,
            'count' => $count,
            'created' => $run->getResult()->getCreated(),
            'messages' => $run->getResult()->getMessages(),
            'durationMs' => $run->getDurationMs(),
        ]);
    }

    public function statsAction(): JsonResponse
    {
        $this->denyAccessUnlessGranted(PageVoter::READ, self::LEGACY_CONTROLLER);

        return new JsonResponse($this->buildStats());
    }

    public function maintenanceAction(Request $request): JsonResponse
    {
        if (!$this->isGranted(PageVoter::UPDATE, self::LEGACY_CONTROLLER)) {
            return new JsonResponse(['success' => false, 'error' => 'You do not have permission to run maintenance tasks.'], Response::HTTP_FORBIDDEN);
        }

        $payload = $this->getPayload($request);
        $action = (string) ($payload['action'] ?? '');
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        $start = microtime(true);

        try {
            switch ($action) {
                case 'search_index':
                    Search::indexation((bool) ($payload['full'] ?? false));
                    $message = 'Search index rebuilt.';
                    break;
                case 'category_tree':
                    Category::regenerateEntireNtree();
                    $message = 'Category tree (nested set) regenerated.';
                    break;
                default:
                    return new JsonResponse(['success' => false, 'error' => sprintf('Unknown maintenance action "%s".', $action)], Response::HTTP_BAD_REQUEST);
            }
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'error' => sprintf('%s: %s', get_class($e), $e->getMessage())], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'success' => true,
            'message' => $message,
            'durationMs' => (int) round((microtime(true) - $start) * 1000),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildStats(): array
    {
        return [
            'counters' => $this->statisticsProvider->getCounters(),
            'database' => $this->statisticsProvider->getDatabaseSize(),
            'tables' => $this->statisticsProvider->getTableSizes(20),
            'ordersPerMonth' => $this->statisticsProvider->getOrdersPerMonth(12),
            'categoryDepth' => $this->statisticsProvider->getCategoryDepthDistribution(),
            'recentRuns' => $this->runLogger->getRecentRuns(25),
            'runTotals' => $this->runLogger->getTotalsByGenerator(),
            'generatedAt' => date('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(Request $request): array
    {
        $content = (string) $request->getContent();
        if ('' !== $content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $request->request->all();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildGenerationContext(array $payload): GenerationContext
    {
        $context = Context::getContext();
        $shopId = (int) ($payload['shopId'] ?? $context->shop->id);
        $shop = new Shop($shopId);
        if (!$shop->id) {
            $shopId = (int) $context->shop->id;
            $shop = $context->shop;
        }
        $langId = (int) ($payload['langId'] ?? $context->language->id);

        return new GenerationContext($shopId, (int) $shop->id_shop_group, $langId ?: (int) $context->language->id);
    }
}
