<?php

/**
 * 2007-2019 PrestaShop SA and Contributors
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
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2019 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

namespace PrestaShopBundle\Translation;

use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Adapter\Localization\LegacyTranslator;
use PrestaShopBundle\Install\Language;
use PrestaShopBundle\Translation\Loader\SqlTranslationLoader;
use Symfony\Component\Translation\Exception\InvalidArgumentException;
use Symfony\Component\Translation\Loader\XliffFileLoader;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

class NewTranslator implements TranslatorInterface, TranslatorBagInterface
{
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public static $regexSprintfParams = '#(?:%%|%(?:[0-9]+\$)?[+-]?(?:[ 0]|\'.)?-?[0-9]*(?:\.[0-9]+)?[bcdeufFosxX])#';
    public static $regexClassicParams = '/%\w+%/';

    public function __construct($translator, $cacheDir)
    {
        $this->translator = $translator;
        $locale = $this->translator->getLocale();

        if (is_dir($cacheDir)) {
            $cache_file = Finder::create()
                ->files()
                ->in($cacheDir)
                ->depth('==0')
                ->name('*.' . $locale . '.*');
            (new Filesystem())->remove($cache_file);
        }

        $adminContext = defined('_PS_ADMIN_DIR_');
        $this->translator->addLoader('xlf', new XliffFileLoader());

        $sqlTranslationLoader = new SqlTranslationLoader();

        $this->translator->addLoader('db', $sqlTranslationLoader);
        $notName = $adminContext ? '^Shop*' : '^Admin*';

        $finder = Finder::create()
            ->files()
            ->name('*.' . $locale . '.xlf')
            ->notName($notName)
            ->in($this->getTranslationResourcesDirectories());

        foreach ($finder as $file) {
            list($domain, $locale, $format) = explode('.', $file->getBasename(), 3);

            $this->addResource($format, $file, $locale, $domain);
            // if (!$this->language instanceof PrestashopBundle\Install\Language) {
                //$this->addResource('db', $domain . '.' . $locale . '.db', $locale, $domain);
            // }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        if (isset($parameters['legacy'])) {
            $legacy = $parameters['legacy'];
            unset($parameters['legacy']);
        }

        $translated = $this->translator->trans($id, array(), $this->normalizeDomain($domain), $locale);

        // @todo to remove after the legacy translation system has ben phased out
        if ($this->shouldFallbackToLegacyModuleTranslation($id, $domain, $translated)) {
            return $this->translateUsingLegacySystem($id, $parameters, $domain, $locale);
        }

        if (isset($legacy) && 'htmlspecialchars' === $legacy) {
            $translated = call_user_func($legacy, $translated, ENT_NOQUOTES);
        } elseif (isset($legacy)) {
            $translated = call_user_func($legacy, $translated);
        }

        if (!empty($parameters) && $this->isSprintfString($id)) {
            $translated = vsprintf($translated, $parameters);
        } elseif (!empty($parameters)) {
            $translated = strtr($translated, $parameters);
        }

        return $translated;
    }

    /**
     * Translates the given choice message by choosing a translation according to a number.
     *
     * @param string $id The message id (may also be an object that can be cast to string)
     * @param int $number The number to use to find the index of the message
     * @param array $parameters An array of parameters for the message
     * @param string|null $domain The domain for the message or null to use the default
     * @param string|null $locale The locale or null to use the default
     *
     * @return string The translated string
     *
     * @throws InvalidArgumentException If the locale contains invalid characters
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        if (null !== $domain) {
            $domain = str_replace('.', '', $domain);
        }

        if (!$this->isSprintfString($id)) {
            return $this->translator->transChoice($id, $number, $parameters, $domain, $locale);
        }

        return vsprintf($this->translator->transChoice($id, $number, array(), $domain, $locale), $parameters);
    }

    /**
     * @param string $string
     *
     * @return bool
     */
    final private function isSprintfString($string)
    {
        return (bool) preg_match_all(static::$regexSprintfParams, $string)
            && !(bool) preg_match_all(static::$regexClassicParams, $string);
    }

    /**
     * Tries to translate the provided message using the legacy system
     *
     * @param string $message
     * @param array $parameters
     * @param string $domain
     * @param string|null $locale
     *
     * @return mixed|string
     *
     * @throws \Exception
     */
    private function translateUsingLegacySystem($message, array $parameters, $domain, $locale = null)
    {
        $domainParts = explode('.', $domain);
        if (count($domainParts) < 2) {
            throw new InvalidArgumentException(sprintf('Invalid domain: "%s"', $domain));
        }

        $moduleName = strtolower($domainParts[1]);
        $sourceFile = (!empty($domainParts[2])) ? strtolower($domainParts[2]) : $moduleName;

        // translate using the legacy system WITHOUT fallback to the new system (to avoid infinite loop)
        return (new LegacyTranslator())->translate($moduleName, $message, $sourceFile, $parameters, false, $locale, false);
    }

    /**
     * Indicates if we should try and translate the provided wording using the legacy system.
     *
     * @param string $message Message to translate
     * @param string $domain Translation domain
     * @param string $translated Message after first translation attempt
     *
     * @return bool
     */
    private function shouldFallbackToLegacyModuleTranslation($message, $domain, $translated)
    {
        return
            $message === $translated
            && 'Modules.' === substr($domain, 0, 8)
            && (
                !method_exists($this, 'getCatalogue')
                || !$this->translator->getCatalogue()->has($message, $this->normalizeDomain($domain))
            )
            ;
    }

    /**
     * Returns the domain without separating dots
     *
     * @param string|null $domain Domain name
     *
     * @return string|null
     */
    private function normalizeDomain($domain)
    {
        // remove up to two dots from the domain name
        // (because legacy domain translations CAN have dots in the third part)
        $normalizedDomain = (!empty($domain)) ?
            (new DomainNormalizer())->normalize($domain)
            : null;

        return $normalizedDomain;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        return $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function getCatalogue($locale = null)
    {
        return $this->translator->getCatalogue($locale);
    }

    /**
     * Performs a reverse search in the catalogue and returns the translation key if found.
     * AVOID USING THIS, IT PROVIDES APPROXIMATE RESULTS.
     *
     * @param string $translated Translated string
     * @param string $domain Translation domain
     * @param string|null $locale Unused
     *
     * @return string The translation
     *
     * @deprecated This method should not be used and will be removed
     */
    public function getSourceString($translated, $domain, $locale = null)
    {
        if (empty($domain)) {
            return $translated;
        }

        $domain = str_replace('.', '', $domain);
        $contextCatalog = $this->translator->getCatalogue()->all($domain);

        if ($untranslated = array_search($translated, $contextCatalog)) {
            return $untranslated;
        }

        return $translated;
    }

    /**
     * @return array
     */
    protected function getTranslationResourcesDirectories()
    {
        $locations = array(_PS_ROOT_DIR_ . '/app/Resources/translations');

        // TODO see how to inject this "shop" dependency
        /* if (null !== $this->shop) {
            $activeThemeLocation = _PS_ROOT_DIR_ . '/themes/' . $this->shop->theme_name . '/translations';
            if (is_dir($activeThemeLocation)) {
                $locations[] = $activeThemeLocation;
            }
        } */

        return $locations;
    }

    public function addresource($format, $resource, $locale, $domain = null)
    {
        $this->translator->addResource($format, $resource, $locale, $domain);
        $this->translator->addResource('db', $domain . '.' . $locale . '.db', $locale, $domain);
    }
}