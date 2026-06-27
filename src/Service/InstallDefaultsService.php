<?php declare(strict_types=1);

namespace InternetArchiveInboundSync\Service;

use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * Seed import defaults on install/upgrade when not already configured.
 */
class InstallDefaultsService
{
    public static function seed(ServiceLocatorInterface $services): void
    {
        $settings = $services->get('Omeka\Settings');
        $api = $services->get('Omeka\ApiManager');
        $p = ModuleSettings::KEY_PREFIX;

        if (!$settings->get($p . 'default_resource_template_id')) {
            $templates = $api->search('resource_templates', ['limit' => 100])->getContent();
            $chosen = null;
            foreach ($templates as $template) {
                if (strcasecmp($template->label(), 'Base Resource') === 0) {
                    $chosen = (int) $template->id();
                    break;
                }
            }
            if ($chosen === null && count($templates) === 1) {
                $chosen = (int) reset($templates)->id();
            }
            if ($chosen !== null) {
                $settings->set($p . 'default_resource_template_id', $chosen);
            }
        }

        $rawSiteIds = $settings->get($p . 'default_site_ids');
        $siteIds = is_array($rawSiteIds)
            ? $rawSiteIds
            : json_decode((string) ($rawSiteIds ?: '[]'), true);
        if (!is_array($siteIds) || $siteIds === []) {
            $ids = [];
            foreach ($api->search('sites', ['limit' => 100])->getContent() as $site) {
                $ids[] = (int) $site->id();
            }
            if ($ids !== []) {
                $settings->set($p . 'default_site_ids', json_encode($ids));
            }
        }
    }
}
