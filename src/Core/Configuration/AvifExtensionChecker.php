<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
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
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

namespace PrestaShop\PrestaShop\Core\Configuration;

/**
 * Class AvifExtensionChecker provides object-oriented way to check if AVIF extension is installed and available.
 */
final class AvifExtensionChecker
{
    private $psAdditionalImageAvif;

    public function __construct($psAdditionalImageAvif)
    {
        $this->psAdditionalImageAvif = $psAdditionalImageAvif;
    }

    public function isAvailable()
    {
        return extension_loaded('gd') &&
            version_compare(PHP_VERSION, '8.1') >= 0 &&
            (bool) $this->psAdditionalImageAvif &&
            function_exists('imageavif') &&
            is_callable('imageavif');
        ;
    }

    /**
     *             // We try to use the imageavif() function.
    // It can fail even if `function_exists('imageavif')` returns true.
    // @see https://stackoverflow.com/questions/71739530/php-8-1-imageavif-avif-image-support-has-been-disabled
    // @todo When this issue will be fixed on main OS (Debian, CentOS), we need to remove this patch
    /* try {
    $image = imagecreatetruecolor(250, 250);
    imageavif($image, 'test.avif');
    } catch {

    }*/

}
