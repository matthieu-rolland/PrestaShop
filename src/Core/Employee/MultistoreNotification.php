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

namespace PrestaShop\PrestaShop\Core\Employee;

use PrestaShop\PrestaShop\Adapter\Employee\EmployeeDataProvider;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * MultistoreNotification
 */
class MultistoreNotification implements EmployeeNotificationInterface
{
    public const CHECKBOX_NOTIFICATIONS_KEY = 'multistore_checkbox';

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * MultistoreNotification constructor.
     *
     * @param SessionInterface $session
     */
    public function __construct(SessionInterface $session)
    {
        $this->session = $session;
    }

    /**
     * @param int $employeeId
     * @param string $notificationKey
     * @param array $data
     * @return void
     */
    public function setNotificationData(int $employeeId, string $notificationKey, array $data): void
    {
        $notificationData = $this->getNotificationData($employeeId, $notificationKey);
        $newData = [];
        $notificationData[$notificationKey] = $newData;
    }

    /**
     * @param int $employeeId
     * @param string $notificationKey
     * @return ?array
     */
    public function getNotificationData(int $employeeId, string $notificationKey): ?array
    {
        $employeeDataProvider = new EmployeeDataProvider();
        $employeeNotifications = $employeeDataProvider->getNotifications($employeeId);
        $employeeNotifications ?? [];

        if (empty($employeeNotifications[$notificationKey])) {
            return null;
        }

        $employeeNotifications = json_decode($employeeNotifications, true);

        return $employeeNotifications[$notificationKey] ?? null;
    }

    public function displayMultistoreCheckboxNotification()
    {
        $employeeDataProvider = new EmployeeDataProvider();
        $notificationsData = $employeeDataProvider->getNotifications(1);
        if (empty($notificationsData[self::CHECKBOX_NOTIFICATIONS_KEY]) || $notificationsData[self::CHECKBOX_NOTIFICATIONS_KEY]) {
            $this->session->getFlashBag()->add('success', 'If you want to apply specific settings to a store or a group of shops, you need to select the parameter to modify, bring your modifications and then save.');
        }
    }

    private function initAbsentData()
    {

    }

}
