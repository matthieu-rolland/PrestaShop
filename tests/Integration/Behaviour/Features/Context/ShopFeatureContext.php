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

namespace Tests\Integration\Behaviour\Features\Context;

use Configuration;
use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Query\SearchShops;
use RuntimeException;
use Shop;
use Tests\Integration\Behaviour\Features\Context\Domain\AbstractDomainFeatureContext;

class ShopFeatureContext extends AbstractDomainFeatureContext
{
    const LAST_FOUND_SHOPS_KEY = 'LAST_FOUND_SHOPS';

    /**
     * @Given single shop :shopReference context is loaded
     *
     * @param string $shopReference
     */
    public function loadSingleShopContext(string $shopReference): void
    {
        /** @var Shop $shop */
        $shop = SharedStorage::getStorage()->get($shopReference);

        Shop::setContext(Shop::CONTEXT_SHOP, $shop->id);
    }

    /**
     * @Given shop :reference with name :shopName exists
     *
     * @param string $reference
     * @param string $shopName
     */
    public function shopWithNameExists(string $reference, string $shopName): void
    {
        $shopId = Shop::getIdByName($shopName);

        if (false === $shopId) {
            throw new RuntimeException(sprintf('Shop with name "%s" does not exist', $shopName));
        }

        SharedStorage::getStorage()->set($reference, new Shop($shopId));
    }

    /**
     * @Given single shop context is loaded
     */
    public function singleShopContextIsLoaded(): void
    {
        Shop::setContext(Shop::CONTEXT_SHOP, Configuration::get('PS_SHOP_DEFAULT'));
    }

    /**
     * @Given multiple shop context is loaded
     */
    public function multipleShopContextIsLoaded(): void
    {
        Shop::setContext(Shop::CONTEXT_ALL);
    }

    /**
     * @Given I add a shop :reference with name :shopName
     *
     * @param string $reference
     * @param string $shopName
     */
    public function addShop(string $reference, string $shopName): void
    {
        $shop = new Shop();
        $shop->active = true;
        $shop->id_shop_group = 1;
        $shop->id_category = 2;
        $shop->theme_name = _THEME_NAME_;
        $shop->name = $shopName;
        if (!$shop->add()) {
            throw new RuntimeException(sprintf('Could not create shop: %s', Db::getInstance()->getMsgError()));
        }
        $shop->setTheme();

        SharedStorage::getStorage()->set($reference, $shop);
    }

    /**
     * @When I search for shops with the term :searchTerm
     *
     * @param string $searchTerm
     */
    public function searchShopsWithTerm(string $searchTerm): void
    {
        try {
            $shops = $this->getQueryBus()->handle(new SearchShops($searchTerm));
        } catch (ShopException $e) {
            $this->setLastException($e);

            return;
        }

        $this->setLastSearchShopsResult(!empty($shops) ? $shops['shops'] : []);
    }

    /**
     * @Then I should get the following shop results:
     *
     * @param TableNode $table
     */
    public function assertFoundShops(TableNode $table): void
    {
        $expectedShops = $table->getColumnsHash();
        $foundShops = $this->getLastSearchShopsResult();

        foreach ($expectedShops as $key => $currentExpectedShop) {
            $wasCurrentExpectedShopFound = false;
            foreach ($foundShops as $currentFoundShop) {
                if ($currentExpectedShop['id'] == $currentFoundShop['id']) {
                    $wasCurrentExpectedShopFound = true;
                    Assert::assertEquals(
                        $currentExpectedShop['name'],
                        $currentFoundShop['name'],
                        sprintf(
                            'Expected and found shops don\'t have the same name (%s and %s)',
                            $currentExpectedShop['name'],
                            $currentFoundShop['name']
                        )
                    );
                    Assert::assertEquals(
                        $currentExpectedShop['group_name'],
                        $currentFoundShop['group_name'],
                        sprintf(
                            'Expected and found shops\'s groups don\'t match (%s and %s)',
                            $currentExpectedShop['group_name'],
                            $currentFoundShop['group_name']
                        )
                    );
                    continue;
                }
            }
            if (!$wasCurrentExpectedShopFound) {
                throw new RuntimeException(sprintf(
                    'Expected shop with name %s and id %s was not found',
                    $currentExpectedShop['name'],
                    $currentExpectedShop['id']
                ));
            }
        }
    }

    /**
     * Set the list of found shops in the last research
     *
     * @param array $shops
     */
    private function setLastSearchShopsResult(array $shops): void
    {
        $this->getSharedStorage()->set(self::LAST_FOUND_SHOPS_KEY, $shops);
    }

    /**
     * Get a list of shop from the last research
     *
     * @return array
     */
    private function getLastSearchShopsResult(): array
    {
        return $this->getSharedStorage()->get(self::LAST_FOUND_SHOPS_KEY);
    }

    /**
     * @Then I should get a ShopException
     */
    public function assertShopException(): void
    {
        $this->assertLastErrorIs(
            ShopException::class
        );
    }
}
