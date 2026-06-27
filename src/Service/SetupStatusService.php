<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Administrator setup checklist for plug-and-play imports and public display.
 */
class SetupStatusService
{
    protected ModuleSettings $moduleSettings;

    public function __construct(ModuleSettings $moduleSettings)
    {
        $this->moduleSettings = $moduleSettings;
    }

    /**
     * @return array{
     *   ready: bool,
     *   open_count: int,
     *   required_open_count: int,
     *   items: array<int, array{
     *     id: string,
     *     ok: bool,
     *     severity: string,
     *     label: string,
     *     detail: string,
     *     action: string
     *   }>
     * }
     */
    public function getStatus(ServiceLocatorInterface $services): array
    {
        $items = [
            $this->checkDefaultResourceTemplate(),
            $this->checkResourceTemplatesExist($services),
            $this->checkJobs($services),
            $this->checkIiifIngester($services),
            $this->checkSiteMediaRenderBlocks($services),
            $this->checkHtmlIframeGuidance(),
            $this->checkReviewImportedMetadata(),
        ];

        $blocking = array_filter(
            $items,
            fn (array $item): bool => !$item['ok'] && $item['severity'] === 'error'
        );
        $open = array_filter($items, fn (array $item): bool => !$item['ok']);

        return [
            'ready' => $blocking === [],
            'open_count' => count($open),
            'required_open_count' => count($blocking),
            'items' => $items,
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkDefaultResourceTemplate(): array
    {
        $id = $this->moduleSettings->defaultResourceTemplateId();
        $ok = $id !== null && $id > 0;

        return [
            'id' => 'default_template',
            'ok' => $ok,
            'severity' => 'warning',
            'label' => 'Default resource template is set',
            'detail' => $ok
                ? 'The Import form pre-selects your default resource template.'
                : 'Choose a default template so every import does not require re-selecting one (you can still change it per run).',
            'action' => 'Modules → Configure → Default resource template',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkResourceTemplatesExist(ServiceLocatorInterface $services): array
    {
        $api = $services->get('Omeka\ApiManager');
        $count = $api->search('resource_templates', ['limit' => 0])->getTotalResults();
        $ok = $count > 0;

        return [
            'id' => 'resource_templates',
            'ok' => $ok,
            'severity' => 'error',
            'label' => 'At least one resource template exists',
            'detail' => $ok
                ? sprintf('%d resource template(s) available.', $count)
                : 'Omeka needs a resource template before items can be imported.',
            'action' => 'Admin → Resources → Resource templates → Add new resource template',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkJobs(ServiceLocatorInterface $services): array
    {
        $conn = $services->get('Omeka\Connection');
        $completed = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM job WHERE status = 'completed'"
        );
        $stuck = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM job WHERE status IN ('starting', 'in_progress')"
        );
        $ok = $completed > 0;

        $detail = $completed > 0
            ? sprintf('%d background job(s) have completed successfully.', $completed)
            : 'Imports are queued as background jobs. Run a dry run or small import, then confirm jobs finish under Admin → Jobs.';
        if ($stuck > 0 && $completed === 0) {
            $detail .= sprintf(' %d job(s) are currently queued or running.', $stuck);
        }

        return [
            'id' => 'jobs',
            'ok' => $ok,
            'severity' => 'warning',
            'label' => 'Background jobs have completed at least once',
            'detail' => $detail,
            'action' => 'Admin → Jobs (verify jobs move from starting to completed). On production, ensure PHP CLI / cron can run omeka job:send.',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkIiifIngester(ServiceLocatorInterface $services): array
    {
        $config = $services->get('Config');
        $invokables = $config['media_ingesters']['invokables'] ?? [];
        $factories = $config['media_ingesters']['factories'] ?? [];
        $ok = isset($invokables['iiif_presentation']) || isset($factories['iiif_presentation']);

        return [
            'id' => 'iiif',
            'ok' => $ok,
            'severity' => 'warning',
            'label' => 'IIIF Presentation media ingester is available',
            'detail' => $ok
                ? 'Image and single-PDF imports can add IIIF Presentation media rows.'
                : 'Imports still create thumbnail and Internet Archive embed media, but skip IIIF Presentation rows until IIIF support is enabled in Omeka.',
            'action' => 'Enable IIIF Presentation in Omeka (core ingester). Check Admin → System information if unsure.',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkSiteMediaRenderBlocks(ServiceLocatorInterface $services): array
    {
        $conn = $services->get('Omeka\Connection');
        $sites = $conn->fetchAllAssociative('SELECT id, title, slug, theme FROM site');
        if ($sites === []) {
            return [
                'id' => 'media_render',
                'ok' => true,
                'severity' => 'warning',
                'label' => 'Public media pages show imported viewers',
                'detail' => 'No public sites yet. When you create a site, add a Media render block to its Media resource page.',
                'action' => 'Admin → Sites → [your site] → Resources → Resource pages → Media',
            ];
        }

        $missing = [];
        foreach ($sites as $site) {
            $theme = (string) ($site['theme'] ?? '');
            if ($theme === '') {
                continue;
            }
            $settingId = 'theme_settings_' . $theme;
            $raw = $conn->fetchOne(
                'SELECT value FROM site_setting WHERE site_id = ? AND id = ?',
                [(int) $site['id'], $settingId]
            );
            if ($raw === false || $raw === null) {
                $missing[] = (string) $site['title'];
                continue;
            }
            $decoded = is_string($raw) ? json_decode($raw, true) : $raw;
            $main = $decoded['resource_page_blocks']['media']['main'] ?? [];
            if (!is_array($main) || !in_array('mediaRender', $main, true)) {
                $missing[] = (string) $site['title'];
            }
        }

        $ok = $missing === [];

        return [
            'id' => 'media_render',
            'ok' => $ok,
            'severity' => 'warning',
            'label' => 'Public media pages show imported viewers',
            'detail' => $ok
                ? 'Every site has a Media render block on its Media resource page.'
                : 'Without a Media render block, /s/{site}/media/{id} pages show titles but no thumbnail, IIIF viewer, or embed iframe. Affected site(s): '
                    . implode(', ', $missing) . '.',
            'action' => 'Admin → Sites → [site] → Resources → Resource pages → Media → add Media render to Main',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkHtmlIframeGuidance(): array
    {
        return [
            'id' => 'html_iframe',
            'ok' => true,
            'severity' => 'info',
            'label' => 'HTML ingester allows Internet Archive embed iframes',
            'detail' => 'Omeka’s HTML purifier may strip <iframe> on import, leaving empty embed media. Allow iframes in HTML purifier settings, or re-import with Repair Internet Archive embed media checked.',
            'action' => 'Admin → Settings → HTML purifier (allow iframe), or use Repair embed on re-import',
        ];
    }

    /**
     * @return array{id: string, ok: bool, severity: string, label: string, detail: string, action: string}
     */
    protected function checkReviewImportedMetadata(): array
    {
        return [
            'id' => 'review_metadata',
            'ok' => true,
            'severity' => 'info',
            'label' => 'Review imported metadata after each import',
            'detail' => 'This module infers titles, subjects, descriptions, and languages from Internet Archive data. Titles, subjects, and descriptions may be concatenated or split incorrectly. Language values are resolved from IA codes when present; items without an IA language (common for images) leave the language property empty. Always open imported items in Omeka and confirm metadata before publishing or syncing back to IA.',
            'action' => 'After import: Admin → Items → open each new item → check title, subject, description, and language fields',
        ];
    }

    protected function checkCompanionOutbound(ServiceLocatorInterface $services): array
    {
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule('InternetArchiveOutboundSync');
        $active = $module && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;

        return [
            'id' => 'companion_outbound',
            'ok' => true,
            'severity' => 'info',
            'label' => 'Optional: IA Outbound module',
            'detail' => $active
                ? 'IA Outbound is installed for pushing Omeka metadata to Internet Archive.'
                : 'This module works on its own. Install IA Outbound separately if you also publish to an Internet Archive collection (requires an IA collection and S3 API keys).',
            'action' => 'Admin → Modules → IA Outbound',
        ];
    }
}
