<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

/**
 * CApiChannelsManager class summary
 *
 * @package Channels
 */

class CApiCoreUserGroupsManager extends \Aurora\System\Managers\AbstractManager
{
	/**
	 * @var \Aurora\System\Managers\Eav\Manager
	 */
	public $oEavManager = null;
	
	/**
	 * @param \Aurora\System\Managers\GlobalManager &$oManager
	 */
	public function __construct(\Aurora\System\Managers\GlobalManager &$oManager, $sForcedStorage = '', \Aurora\System\Module\AbstractModule $oModule = null)
	{
		parent::__construct('usergroups', $oManager, $oModule);
		
		$this->oEavManager = \Aurora\System\Api::GetSystemManager('eav', 'db');
	}

	/**
	 * @param int $iPage
	 * @param int $iItemsPerPage
	 * @param string $sOrderBy Default value is **Login**
	 * @param bool $iOrderType Default value is **\ESortOrder::ASC**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return array|false [Id => [Login, Description]]
	 */
	public function getUserGroupsList($iPage, $iItemsPerPage, $sOrderBy = 'Login', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResult = false;
		try
		{
			$aResultGroups = $this->oEavManager->getObjects(
				'CUserGroup', 
				array('UrlIdentifier', 'IdTenant'),
				$iPage,
				$iItemsPerPage,
				array(
					'UrlIdentifier' => '%'.$sSearchDesc.'%'
				),
				$sOrderBy,
				$iOrderType
			);
			
			foreach($aResultGroups as $oUserGroup)
			{
				$aResult[$oUserGroup->EntityId] = array(
					$oUserGroup->UrlIdentifier,
					$oUserGroup->IdTenant
				);
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $aResult;
	}

	/**
	 * @param string $sSearchDesc Default value is empty string
	 *
	 * @return int|false
	 */
	public function getUserGroupsCount($sSearchDesc = '')
	{
		$iResult = false;
		try
		{
			$aResults = $this->oEavManager->getObjectsCount(
				'CUserGroups', 
				array(
					'UrlIdentifier' => '%'.$sSearchDesc.'%'
				)
			);
			
			$iResult = count($aResults);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $iResult;
	}

	/**
	 * @param int $iGroupId
	 *
	 * @return CChannel
	 */
	public function getUserGroupById($iGroupId)
	{
		$oGroup = null;
		try
		{
			$oResult = $this->oEavManager->getObjectById($iGroupId);
			
			if ($oResult instanceOf \CChannel)
			{
				$oGroup = $oResult;
			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $oGroup;
	}


	/**
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function isExists(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
//			$aResultChannels = $this->oEavManager->getObjects(
//				'CUserGroup',
//				array('Login'),
//				0,
//				0,
//				array('Login' => $oGroup->Login)
//			);
//
//			if ($aResultChannels)
//			{
//				foreach($aResultChannels as $oObject)
//				{
//					if ($oObject->EntityId !== $oGroup->EntityId)
//					{
//						$bResult = true;
//						break;
//					}
//				}
//			}
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function saveUserGroup(CUserGroup &$oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$this->oEavManager->saveObject($oGroup))
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupAlreadyExists);
				}
			}

			$bResult = true;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}

		return $bResult;
	}

	/**
	 * @param CUserGroup $oGroup
	 *
	 * @return bool
	 */
	public function updateUserGroup(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
			if ($oGroup->validate())
			{
				if (!$this->isExists($oGroup))
				{
					if (!$this->oEavManager->saveObject($oGroup))
					{
						throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupCreateFailed);
					}
				}
				else
				{
					throw new \Aurora\System\Exceptions\ManagerException(Errs::UserGroupsManager_UserGroupDoesNotExist);
				}
			}

			$bResult = true;
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$bResult = false;
			$this->setLastException($oException);
		}
		return $bResult;
	}

	/**
	 * @todo
	 * @param CUserGroup $oGroup
	 *
	 * @throws $oException
	 *
	 * @return bool
	 */
	public function deleteUserGroup(CUserGroup $oGroup)
	{
		$bResult = false;
		try
		{
//			$oTenantsApi = $this->oModule->GetManager('tenants');
//			
//			if ($oTenantsApi && !$oTenantsApi->deleteTenantsByChannelId($oGroup->EntityId, true))
//			{
//				$oException = $oTenantsApi->GetLastException();
//				if ($oException)
//				{
//					throw $oException;
//				}
//			}

//			$bResult = $this->oEavManager->deleteObject($oGroup->EntityId);
		}
		catch (\Aurora\System\Exceptions\BaseException $oException)
		{
			$this->setLastException($oException);
		}

		return $bResult;
	}
}