<?php

/**
 * 2007-2019 PrestaShop and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
namespace PrestaShopBundle\Translation;

use PrestaShopBundle\Install\Language;
use PrestaShopBundle\Translation\Loader\SqlTranslationLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Loader\XliffFileLoader;

trait ContextTranslationTrait
{
    public function dropTranslationCache($cacheDir)
    {
        if (is_dir($cacheDir)) {
            $cache_file = Finder::create()
                ->files()
                ->in($cacheDir)
                ->depth('==0')
                ->name('*.' . $locale . '.*');
            (new Filesystem())->remove($cache_file);
        }
    }

    public function addTranslationLoaders($shop)
    {
        $this->addLoader('xlf', new XliffFileLoader());

        $sqlTranslationLoader = new SqlTranslationLoader();
        //TODO see how to get this dependency in new translator
        if (null !== shop) {
            $sqlTranslationLoader->setTheme($shop->theme);
        }

        $this->addLoader('db', $sqlTranslationLoader);
    }

    public function addTranslationResources($language)
    {
        $adminContext = defined('_PS_ADMIN_DIR_');
        $notName = $adminContext ? '^Shop*' : '^Admin*';
        $finder = Finder::create()
            ->files()
            ->name('*.' . $locale . '.xlf')
            ->notName($notName)
            ->in($this->getTranslationResourcesDirectories());

        foreach ($finder as $file) {
            list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);

            $this->addResource($format, $file, $locale, $domain);
            if (!$language instanceof PrestashopBundle\Install\Language) {
                $this->addResource('db', $domain . '.' . $locale . '.db', $locale, $domain);
            }
        }
    }
}