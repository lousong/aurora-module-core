<?php

class CoreModule extends AApiModule
{
	public $oApiTenantsManager = null;
	
	public $oApiChannelsManager = null;
	
	public $oApiUsersManager = null;
	
	protected $aSettingsMap = array(
		'LoggingLevel' => array(ELogLevel::Full, 'spec', 'ELogLevel'),
	);

	/**
	 * Initializes Core Module.
	 */
	public function init() {
		
		$this->incClass('channel');
		$this->incClass('usergroup');		
		$this->incClass('tenant');
		$this->incClass('socials');
		$this->incClass('user');
		
		$this->oApiTenantsManager = $this->GetManager('tenants');
		$this->oApiChannelsManager = $this->GetManager('channels');
		$this->oApiUsersManager = $this->GetManager('users');
		
		$this->AddEntries(array(
				'ping' => 'EntryPing',
				'pull' => 'EntryPull',
				'plugins' => 'EntryPlugins',
				'mobile' => 'EntryMobile',
				'speclogon' => 'EntrySpeclogon',
				'speclogoff' => 'EntrySpeclogoff',
				'sso' => 'EntrySso',
				'postlogin' => 'EntryPostlogin'
			)
		);
		
		$this->subscribeEvent('CreateAccount', array($this, 'onAccountCreate'));
	}
	
	/**
	 * Does some pending actions to be executed when you log in.
	 * 
	 * @return boolean
	 */
	public function DoServerInitializations()
	{
		$iUserId = \CApi::getAuthenticatedUserId();

		$bResult = false;

		$oApiIntegrator = \CApi::GetSystemManager('integrator');

		if ($iUserId && $oApiIntegrator)
		{
			$oApiIntegrator->resetCookies();
		}

		if ($this->oApiCapabilityManager->isGlobalContactsSupported($iUserId, true))
		{
			$bResult = \CApi::ExecuteMethod('Contact::SynchronizeExternalContacts', array('UserId' => $iUserId));
		}

		$oCacher = \CApi::Cacher();

		$bDoGC = false;
		$bDoHepdeskClear = false;
		if ($oCacher && $oCacher->IsInited())
		{
			$iTime = $oCacher->GetTimer('Cache/ClearFileCache');
			if (0 === $iTime || $iTime + 60 * 60 * 24 < time())
			{
				if ($oCacher->SetTimer('Cache/ClearFileCache'))
				{
					$bDoGC = true;
				}
			}

			if (\CApi::GetModuleManager()->ModuleExists('Helpdesk'))
			{
				$iTime = $oCacher->GetTimer('Cache/ClearHelpdeskUsers');
				if (0 === $iTime || $iTime + 60 * 60 * 24 < time())
				{
					if ($oCacher->SetTimer('Cache/ClearHelpdeskUsers'))
					{
						$bDoHepdeskClear = true;
					}
				}
			}
		}

		if ($bDoGC)
		{
			\CApi::Log('GC: FileCache / Start');
			$oApiFileCache = \Capi::GetSystemManager('filecache');
			$oApiFileCache->gc();
			$oCacher->gc();
			\CApi::Log('GC: FileCache / End');
		}

		if ($bDoHepdeskClear && \CApi::GetModuleManager()->ModuleExists('Helpdesk'))
		{
			\CApi::ExecuteMethod('Helpdesk::ClearUnregistredUsers');
			\CApi::ExecuteMethod('Helpdesk::ClearAllOnline');
		}

		return $bResult;
	}
	
	/**
	 * Method is used for checking internet connection.
	 * 
	 * @return 'Pong'
	 */
	public function Ping()
	{
		return 'Pong';
	}	
	
	/**
	 * Obtaines module settings for authenticated user.
	 * 
	 * @return array
	 */
	public function GetAppData()
	{
		$oUser = \CApi::getAuthenticatedUser();
		return $oUser && $oUser->Role === 0 ? array(
			'SiteName' => \CApi::GetSettingsConf('SiteName'),
			'LicenseKey' => \CApi::GetSettingsConf('LicenseKey'),
			'DBHost' => \CApi::GetSettingsConf('DBHost'),
			'DBName' => \CApi::GetSettingsConf('DBName'),
			'DBLogin' => \CApi::GetSettingsConf('DBLogin'),
			'DefaultLanguage' => \CApi::GetSettingsConf('DefaultLanguage'),
			'DefaultTimeFormat' => \CApi::GetSettingsConf('DefaultTimeFormat'),
			'DefaultDateFormat' => \CApi::GetSettingsConf('DefaultDateFormat'),
			'AppStyleImage' => \CApi::GetSettingsConf('AppStyleImage'),
			'AdminLogin' => \CApi::GetSettingsConf('AdminLogin'),
			'AdminHasPassword' => !empty(\CApi::GetSettingsConf('AdminPassword')),
			'EnableLogging' => \CApi::GetSettingsConf('EnableLogging'),
			'EnableEventLogging' => \CApi::GetSettingsConf('EnableEventLogging'),
			'LoggingLevel' => \CApi::GetSettingsConf('LoggingLevel')
		) : array(
			'SiteName' => \CApi::GetSettingsConf('SiteName'),
			'DefaultLanguage' => \CApi::GetSettingsConf('DefaultLanguage'),
			'DefaultTimeFormat' => \CApi::GetSettingsConf('DefaultTimeFormat'),
			'DefaultDateFormat' => \CApi::GetSettingsConf('DefaultDateFormat'),
			'AppStyleImage' => \CApi::GetSettingsConf('AppStyleImage')
		);
	}
	
	/**
	 * Updates specified settings if super administrator is authenticated.
	 * 
	 * @param string $LicenseKey Value of license key.
	 * @param string $DbLogin Database login.
	 * @param string $DbPassword Database password.
	 * @param string $DbName Database name.
	 * @param string $DbHost Database host.
	 * @param string $AdminLogin Login for super administrator.
	 * @param string $Password Current password for super administrator.
	 * @param string $NewPassword New password for super administrator.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function UpdateSettings($LicenseKey = null, $DbLogin = null, 
			$DbPassword = null, $DbName = null, $DbHost = null,
			$AdminLogin = null, $Password = null, $NewPassword = null)
	{
		$this->verifyAdminAccess();
		
		$oSettings =& CApi::GetSettings();
		if ($LicenseKey !== null)
		{
			$oSettings->SetConf('LicenseKey', $LicenseKey);
		}
		if ($DbLogin !== null)
		{
			$oSettings->SetConf('DBLogin', $DbLogin);
		}
		if ($DbPassword !== null)
		{
			$oSettings->SetConf('DBPassword', $DbPassword);
		}
		if ($DbName !== null)
		{
			$oSettings->SetConf('DBName', $DbName);
		}
		if ($DbHost !== null)
		{
			$oSettings->SetConf('DBHost', $DbHost);
		}
		if ($AdminLogin !== null && $AdminLogin !== $oSettings->GetConf('AdminLogin'))
		{
			$this->broadcastEvent('CheckAccountExists', array($AdminLogin));
		
			$oSettings->SetConf('AdminLogin', $AdminLogin);
		}
		if ((empty($oSettings->GetConf('AdminPassword')) && empty($Password) || !empty($Password)) && !empty($NewPassword))
		{
			if (empty($oSettings->GetConf('AdminPassword')) || 
					crypt(trim($Password), \CApi::$sSalt) === $oSettings->GetConf('AdminPassword'))
			{
				$oSettings->SetConf('AdminPassword', crypt(trim($NewPassword), \CApi::$sSalt));
			}
			else
			{
				throw new \System\Exceptions\ClientException(Errs::UserManager_AccountOldPasswordNotCorrect);
			}
		}
		return $oSettings->Save();
	}
	
	/**
	 * Obtains tenant list if super administrator is authenticated.
	 * 
	 * @return array {
	 *		*int* **id** Tenant identificator
	 *		*string* **name** Tenant name
	 * }
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function GetTenants()
	{
		$this->verifyAdminAccess();
		
		$aTenants = $this->oApiTenantsManager->getTenantList();
		$aItems = array();

		foreach ($aTenants as $oTenat)
		{
			$aItems[] = array(
				'id' => $oTenat->iId,
				'name' => $oTenat->Name
			);
		}
		
		return $aItems;
	}
	
	/**
	 * @ignore
	 */
	public function EntryPull()
	{
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN')
		{
			pclose(popen("start /B git pull", "r"));
		}
		else 
		{
			exec("git pull > /dev/null 2>&1 &");
		}
	}
	
	/**
	 * @ignore
	 * @return string
	 */
	public function EntryPlugins()
	{
		$sResult = '';
		$aPaths = $this->oHttp->GetPath();
		$sType = !empty($aPaths[1]) ? trim($aPaths[1]) : '';
		if ('js' === $sType)
		{
			@header('Content-Type: application/javascript; charset=utf-8');
			$sResult = \CApi::Plugin()->CompileJs();
		}
		else if ('images' === $sType)
		{
			if (!empty($aPaths[2]) && !empty($aPaths[3]))
			{
				$oPlugin = \CApi::Plugin()->GetPluginByName($aPaths[2]);
				if ($oPlugin)
				{
					echo $oPlugin->GetImage($aPaths[3]);exit;
				}
			}
		}
		else if ('fonts' === $sType)
		{
			if (!empty($aPaths[2]) && !empty($aPaths[3]))
			{
				$oPlugin = \CApi::Plugin()->GetPluginByName($aPaths[2]);
				if ($oPlugin)
				{
					echo $oPlugin->GetFont($aPaths[3]);exit;
				}
			}
		}	
		
		return $sResult;
	}	
	
	/**
	 * @ignore
	 */
	public function EntryMobile()
	{
		if ($this->oApiCapabilityManager->isNotLite())
		{
			$oApiIntegrator = \CApi::GetSystemManager('integrator');
			$oApiIntegrator->setMobile(true);
		}

		\CApi::Location('./');
	}
	
	/**
	 * @ignore
	 * Creates entry point ?Speclogon that turns on user level of logging.
	 */
	public function EntrySpeclogon()
	{
		\CApi::SpecifiedUserLogging(true);
		\CApi::Location('./');
	}
	
	/**
	 * @ignore
	 * Creates entry point ?Speclogoff that turns off user level of logging.
	 */
	public function EntrySpeclogoff()
	{
		\CApi::SpecifiedUserLogging(false);
		\CApi::Location('./');
	}

	/**
	 * @ignore
	 */
	public function EntrySso()
	{
		$oApiIntegratorManager = \CApi::GetSystemManager('integrator');

		try
		{
			$sHash = $this->oHttp->GetRequest('hash');
			if (!empty($sHash))
			{
				$sData = \CApi::Cacher()->get('SSO:'.$sHash, true);
				$aData = \CApi::DecodeKeyValues($sData);

				if (!empty($aData['Email']) && isset($aData['Password'], $aData['Login']))
				{
					$oAccount = $oApiIntegratorManager->loginToAccount($aData['Email'], $aData['Password'], $aData['Login']);
					if ($oAccount)
					{
						$oApiIntegratorManager->setAccountAsLoggedIn($oAccount);
					}
				}
			}
			else
			{
				$oApiIntegratorManager->logoutAccount();
			}
		}
		catch (\Exception $oExc)
		{
			\CApi::LogException($oExc);
		}

		\CApi::Location('./');		
	}	
	
	/**
	 * @ignore
	 */
	public function EntryPostlogin()
	{
		if (\CApi::GetConf('labs.allow-post-login', false))
		{
			$oApiIntegrator = \CApi::GetSystemManager('integrator');
					
			$sEmail = trim((string) $this->oHttp->GetRequest('Email', ''));
			$sLogin = (string) $this->oHttp->GetRequest('Login', '');
			$sPassword = (string) $this->oHttp->GetRequest('Password', '');

			$sAtDomain = trim(\CApi::GetSettingsConf('WebMail/LoginAtDomainValue'));
			if (\ELoginFormType::Login === (int) \CApi::GetSettingsConf('WebMail/LoginFormType') && 0 < strlen($sAtDomain))
			{
				$sEmail = \api_Utils::GetAccountNameFromEmail($sLogin).'@'.$sAtDomain;
				$sLogin = $sEmail;
			}

			if (0 !== strlen($sPassword) && 0 !== strlen($sEmail.$sLogin))
			{
				try
				{
					$oAccount = $oApiIntegrator->loginToAccount($sEmail, $sPassword, $sLogin);
				}
				catch (\Exception $oException)
				{
					$iErrorCode = \System\Notifications::UnknownError;
					if ($oException instanceof \CApiManagerException)
					{
						switch ($oException->getCode())
						{
							case \Errs::WebMailManager_AccountDisabled:
							case \Errs::WebMailManager_AccountWebmailDisabled:
								$iErrorCode = \System\Notifications::AuthError;
								break;
							case \Errs::UserManager_AccountAuthenticationFailed:
							case \Errs::WebMailManager_AccountAuthentication:
							case \Errs::WebMailManager_NewUserRegistrationDisabled:
							case \Errs::WebMailManager_AccountCreateOnLogin:
							case \Errs::Mail_AccountAuthentication:
							case \Errs::Mail_AccountLoginFailed:
								$iErrorCode = \System\Notifications::AuthError;
								break;
							case \Errs::UserManager_AccountConnectToMailServerFailed:
							case \Errs::WebMailManager_AccountConnectToMailServerFailed:
							case \Errs::Mail_AccountConnectToMailServerFailed:
								$iErrorCode = \System\Notifications::MailServerError;
								break;
							case \Errs::UserManager_LicenseKeyInvalid:
							case \Errs::UserManager_AccountCreateUserLimitReached:
							case \Errs::UserManager_LicenseKeyIsOutdated:
							case \Errs::TenantsManager_AccountCreateUserLimitReached:
								$iErrorCode = \System\Notifications::LicenseProblem;
								break;
							case \Errs::Db_ExceptionError:
								$iErrorCode = \System\Notifications::DataBaseError;
								break;
						}
					}
					$sReditectUrl = \CApi::GetConf('labs.post-login-error-redirect-url', './');
					\CApi::Location($sReditectUrl . '?error=' . $iErrorCode);
					exit;
				}

				if ($oAccount instanceof \CAccount)
				{
					$oApiIntegrator->setAccountAsLoggedIn($oAccount);
				}
			}

			\CApi::Location('./');
		}
	}	
	
	/**
	 * @ignore
	 * Turns on or turns off mobile version.
	 * @param boolean $Mobile Indicates if mobile version should be turned on or turned off.
	 * @return boolean
	 */
	public function SetMobile($Mobile)
	{
		$oApiIntegratorManager = \CApi::GetSystemManager('integrator');
		return $oApiIntegratorManager ? $oApiIntegratorManager->setMobile($Mobile) : false;
	}	
	
	/**
	 * Clears temporary files by cron.
	 * 
	 * @ignore
	 * @todo check if it works.
	 * 
	 * @return bool
	 */
	public function ClearTempFiles()
	{
		$sTempPath = CApi::DataPath().'/temp';
		if (@is_dir($sTempPath))
		{
			$iNow = time();

			$iTime2Run = CApi::GetConf('temp.cron-time-to-run', 10800);
			$iTime2Kill = CApi::GetConf('temp.cron-time-to-kill', 10800);
			$sDataFile = CApi::GetConf('temp.cron-time-file', '.clear.dat');

			$iFiletTime = -1;
			if (@file_exists(CApi::DataPath().'/'.$sDataFile))
			{
				$iFiletTime = (int) @file_get_contents(CApi::DataPath().'/'.$sDataFile);
			}

			if ($iFiletTime === -1 || $iNow - $iFiletTime > $iTime2Run)
			{
				$this->removeDirByTime($sTempPath, $iTime2Kill, $iNow);
				@file_put_contents( CApi::DataPath().'/'.$sDataFile, $iNow);
			}
		}

		return true;
	}

	/**
	 * Recursively deletes temporary files and folders on time.
	 * 
	 * @param string $sTempPath Path to the temporary folder.
	 * @param int $iTime2Kill Interval in seconds at which files needs removing.
	 * @param int $iNow Current Unix timestamp.
	 */
	protected function removeDirByTime($sTempPath, $iTime2Kill, $iNow)
	{
		$iFileCount = 0;
		if (@is_dir($sTempPath))
		{
			$rDirH = @opendir($sTempPath);
			if ($rDirH)
			{
				while (($sFile = @readdir($rDirH)) !== false)
				{
					if ('.' !== $sFile && '..' !== $sFile)
					{
						if (@is_dir($sTempPath.'/'.$sFile))
						{
							$this->removeDirByTime($sTempPath.'/'.$sFile, $iTime2Kill, $iNow);
						}
						else
						{
							$iFileCount++;
						}
					}
				}
				@closedir($rDirH);
			}

			if ($iFileCount > 0)
			{
				if ($this->removeFilesByTime($sTempPath, $iTime2Kill, $iNow))
				{
					@rmdir($sTempPath);
				}
			}
			else
			{
				@rmdir($sTempPath);
			}
		}
	}

	/**
	 * Recursively deletes temporary files on time.
	 * 
	 * @param string $sTempPath Path to the temporary folder.
	 * @param int $iTime2Kill Interval in seconds at which files needs removing.
	 * @param int $iNow Current Unix timestamp.
	 * 
	 * @return boolean
	 */
	protected function removeFilesByTime($sTempPath, $iTime2Kill, $iNow)
	{
		$bResult = true;
		if (@is_dir($sTempPath))
		{
			$rDirH = @opendir($sTempPath);
			if ($rDirH)
			{
				while (($sFile = @readdir($rDirH)) !== false)
				{
					if ($sFile !== '.' && $sFile !== '..')
					{
						if ($iNow - filemtime($sTempPath.'/'.$sFile) > $iTime2Kill)
						{
							@unlink($sTempPath.'/'.$sFile);
						}
						else
						{
							$bResult = false;
						}
					}
				}
				@closedir($rDirH);
			}
		}
		return $bResult;
	}
	
	/**
	 * Creates channel with specified login and description.
	 * 
	 * @param string $Login New channel login.
	 * @param string $Description New channel description.
	 * 
	 * @return int New channel identificator.
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function CreateChannel($Login, $Description = '')
	{
		$this->verifyAdminAccess();
		
		if ($Login !== '')
		{
			$oChannel = \CChannel::createInstance();
			
			$oChannel->Login = $Login;
			
			if ($Description !== '')
			{
				$oChannel->Description = $Description;
			}

			if ($this->oApiChannelsManager->createChannel($oChannel))
			{
				return $oChannel->iId;
			}
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::InvalidInputParameter);
		}
	}
	
	/**
	 * Updates channel.
	 * 
	 * @param int $ChannelId Channel identificator.
	 * @param string $Login New login for channel.
	 * @param string $Description New description for channel.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function UpdateChannel($ChannelId, $Login = '', $Description = '')
	{
		$this->verifyAdminAccess();
		
		if ($ChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($ChannelId);
			
			if ($oChannel)
			{
				if ($Login)
				{
					$oChannel->Login = $Login;
				}
				if ($Description)
				{
					$oChannel->Description = $Description;
				}
				
				return $this->oApiChannelsManager->updateChannel($oChannel);
			}
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * Deletes channel.
	 * 
	 * @param int $iChannelId Identificator of channel to delete.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function DeleteChannel($iChannelId)
	{
		$this->verifyAdminAccess();

		if ($iChannelId > 0)
		{
			$oChannel = $this->oApiChannelsManager->getChannelById($iChannelId);
			
			if ($oChannel)
			{
				return $this->oApiChannelsManager->deleteChannel($oChannel);
			}
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * Creates tenant.
	 * 
	 * @param int $ChannelId Identificator of channel new tenant belongs to.
	 * @param string $Name New tenant name.
	 * @param string $Description New tenant description.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function CreateTenant($ChannelId, $Name, $Description = '')
	{
		$this->verifyAdminAccess();
		
		if ($Name !== '' && $ChannelId > 0)
		{
			$oTenant = \CTenant::createInstance();

			$oTenant->Name = $Name;
			$oTenant->Description = $Description;
			$oTenant->IdChannel = $ChannelId;

			if ($this->oApiTenantsManager->createTenant($oTenant))
			{
				return $oTenant->iId;
			}
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::InvalidInputParameter);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function UpdateTenant($iTenantId = 0, $sName = '', $sDescription = '', $iChannelId = 0)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		
		if ($iTenantId > 0)
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($iTenantId);
			
			if ($oTenant)
			{
				if ($sName)
				{
					$oTenant->Name = $sName;
				}
				if ($sDescription)
				{
					$oTenant->Description = $sDescription;
				}
				if ($iChannelId)
				{
					$oTenant->IdChannel = $iChannelId;
				}
				
				$this->oApiTenantsManager->updateTenant($oTenant);
			}
			
			return $oTenant ? array(
				'iObjectId' => $oTenant->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public function DeleteTenant($iTenantId = 0)
	{
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))

		if ($iTenantId > 0)
		{
			$oTenant = $this->oApiTenantsManager->getTenantById($iTenantId);
			
			if ($oTenant)
			{
				$sTenantSpacePath = PSEVEN_APP_ROOT_PATH.'tenants/'.$oTenant->Name;
				
				if (@is_dir($sTenantSpacePath))
				{
					$this->deleteTree($sTenantSpacePath);
				}
						
				$this->oApiTenantsManager->deleteTenant($oTenant);
			}
			
			return $oTenant ? array(
				'iObjectId' => $oTenant->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	/**
	 * 
	 * @return boolean
	 */
	public static function deleteTree($dir)
	{
		$files = array_diff(scandir($dir), array('.','..'));
			
		foreach ($files as $file) {
			(is_dir("$dir/$file")) ? self::deleteTree("$dir/$file") : unlink("$dir/$file");
		}
		return rmdir($dir);
	}
  
	//TODO is it used by any code?
	/*$iTenantId, $iUserId, $sLogin, $sPassword*/
	public function onAccountCreate($aData, &$oResult)
	{
		$oUser = null;
		
		if (isset($aData['UserId']) && (int)$aData['UserId'] > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($aData['UserId']);
		}
		else
		{
			$oUser = \CUser::createInstance();
			
			$iTenantId = (isset($aData['TenantId'])) ? (int)$aData['TenantId'] : 0;
			if ($iTenantId)
			{
				$oUser->IdTenant = $iTenantId;
			}

			$sUserName = (isset($aData['UserName'])) ? $aData['UserName'] : '';
			if ($sUserName)
			{
				$oUser->Name = $sUserName;
			}
				
			if (!$this->oApiUsersManager->createUser($oUser))
			{
				$oUser = null;
			}
		}
		
		$oResult = $oUser;
	}
	
	/**
	 * @param int $iTenantId
	 * @param string $sName
	 * @param int $iRole
	 * @return boolean
	 * @throws \System\Exceptions\ClientException
	 */
	public function CreateUser($iTenantId = 0, $sName = '', $iRole = \EUserRole::PowerUser)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		if ($iTenantId > 0 && $sName !== '')
		{
			$oUser = \CUser::createInstance();
			
			$oUser->Name = $sName;
			$oUser->IdTenant = $iTenantId;
			$oUser->Role = $iRole;

			$this->oApiUsersManager->createUser($oUser);
			return $oUser ? array(
				'iObjectId' => $oUser->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}

	/**
	 * 
	 * @param int $iUserId
	 * @param string $sUserName
	 * @param int $iTenantId
	 * @param int $iRole
	 * @return boolean
	 * @throws \System\Exceptions\ClientException
	 */
	public function UpdateUser($iUserId = 0, $sUserName = '', $iTenantId = 0, $iRole = -1)
	{
//		$oAccount = $this->getDefaultAccountFromParam();
		
//		if ($this->oApiCapabilityManager->isPersonalContactsSupported($oAccount))
		
		if ($iUserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($iUserId);
			
			if ($oUser)
			{
				$oUser->Name = $sUserName;
				if ($iTenantId !== 0)
				{
					$oUser->IdTenant = $iTenantId;
				}
				if ($iRole !== -1)
				{
					$oUser->Role = $iRole;
				}
				$this->oApiUsersManager->updateUser($oUser);
			}
			
			return $oUser ? array(
				'iObjectId' => $oUser->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	public function UpdateUserObject($oUser)
	{
		$this->oApiUsersManager->updateUser($oUser);
	}
	
	/**
	 * Deletes user.
	 * 
	 * @param int $iUserId User identificator.
	 * 
	 * @return boolean
	 * 
	 * @throws \System\Exceptions\ClientException
	 */
	public function DeleteUser($iUserId = 0)
	{
		if ($iUserId > 0)
		{
			$oUser = $this->oApiUsersManager->getUserById($iUserId);
			
			if ($oUser)
			{
				$this->oApiUsersManager->deleteUser($oUser);
				$this->broadcastEvent($this->GetName() . \AApiModule::$Delimiter . 'AfterDeleteUser', array($oUser->iId));
			}
			
			return $oUser ? array(
				'iObjectId' => $oUser->iId
			) : false;
		}
		else
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::UserNotAllowed);
		}

		return false;
	}
	
	public function GetUser($iUserId = 0)
	{
		$oUser = $this->oApiUsersManager->getUserById((int) $iUserId);
		
		return $oUser ? $oUser : null;
	}
	
	public function GetAdminUser()
	{
		$oUser = new \CUser();
		$oUser->iId = -1;
		$oUser->Role = 0;
		$oUser->Name = 'Administrator';
		
		return $oUser;
	}
	
	public function DeleteEntity($Type, $Id)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->DeleteTenant($Id);
			case 'User':
				return $this->DeleteUser($Id);
		}
		return false;
	}
	
	public function CreateEntity($Type, $Data)
	{
		switch ($Type)
		{
			case 'Tenant':
				$aChannels = $this->oApiChannelsManager->getChannelList(0, 1);
				$iChannelId = count($aChannels) === 1 ? $aChannels[0]->iId : 0;
				return $this->CreateTenant($iChannelId, $Data['Name'], $Data['Description']);
			case 'User':
				$aTenants = $this->oApiTenantsManager->getTenantList(0, 1);
				$iTenantId = count($aTenants) === 1 ? $aTenants[0]->iId : 0;
				return $this->CreateUser($iTenantId, $Data['Name'], $Data['Role']);
		}
		return false;
	}
	
	public function GetEntities($Type)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->GetTenants();
			case 'User':
				return $this->GetUserList();
		}
		return null;
	}
	
	public function GetEntity($Type, $Id)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->GetTenantById($Id);
			case 'User':
				return $this->GetUser($Id);
		}
		return null;
	}
	
	public function SaveEntity($Type, $Data)
	{
		switch ($Type)
		{
			case 'Tenant':
				return $this->UpdateTenant($Data['Id'], $Data['Name'], $Data['Description']);
			case 'User':
				return $this->UpdateUser($Data['Id'], $Data['Name'], 0, $Data['Role']);
		}
		return null;
	}
	
	/**
	 * Creates tables reqired for module work. Creates first channel and tenant if it is necessary.
	 * 
	 * @return boolean
	 */
	public function CreateTables()
	{
		$bResult = false;
		$oSettings =& CApi::GetSettings();
		$oApiEavManager = CApi::GetSystemManager('eav', 'db');
		if ($oApiEavManager->createTablesFromFile())
		{
			if ($oSettings->GetConf('EnableMultiChannel') && $oSettings->GetConf('EnableMultiTenant'))
			{
				$bResult = true;
			}
			else
			{
				$iChannelId = 0;
				$aChannels = $this->oApiChannelsManager->getChannelList(0, 1);
				if (is_array($aChannels) && count($aChannels) === 1)
				{
					$iChannelId = $aChannels[0]->iId;
				}
				else
				{
					$iChannelId = $this->CreateChannel('Default', '');
				}
				if ($iChannelId !== 0)
				{
					if ($oSettings->GetConf('EnableMultiTenant'))
					{
						$bResult = true;
					}
					else
					{
						$aTenants = $this->oApiTenantsManager->getTenantsByChannelId($iChannelId);
						if (is_array($aTenants) && count($aTenants) === 1)
						{
							$bResult = true;
						}
						else
						{
							$mTenantId = $this->CreateTenant($iChannelId, 'Default');
							if (is_int($mTenantId))
							{
								$bResult = true;
							}
						}
					}
				}
			}
		}
		
		return $bResult;
	}
	
	public function TestDbConnection($DbLogin, $DbName, $DbHost, $DbPassword = null)
	{
		$oSettings =& CApi::GetSettings();
		$oSettings->SetConf('DBLogin', $DbLogin);
		if ($DbPassword !== null)
		{
			$oSettings->SetConf('DBPassword', $DbPassword);
		}
		$oSettings->SetConf('DBName', $DbName);
		$oSettings->SetConf('DBHost', $DbHost);
		
		$oApiEavManager = CApi::GetSystemManager('eav', 'db');
		return $oApiEavManager->testStorageConnection();
	}
	
	public function GetUserList($iOffset = 0, $iLimit = 0, $sOrderBy = 'Name', $iOrderType = \ESortOrder::ASC, $sSearchDesc = '')
	{
		$aResults = $this->oApiUsersManager->getUserList($iOffset, $iLimit, $sOrderBy, $iOrderType, $sSearchDesc);
		$aUsers = array();
		foreach($aResults as $oUser)
		{
			$aUsers[] = array(
				'id' => $oUser->iId,
				'name' => $oUser->Name
			);
		}
		return $aUsers;
	}

	public function GetTenantIdByName($sTenantName = '')
	{
		$oTenant = $this->oApiTenantsManager->getTenantIdByName((string) $sTenantName);

		return $oTenant ? $oTenant : null;
	}
	
	public function GetTenantById($iIdTenant)
	{
		$oTenant = $this->oApiTenantsManager->getTenantById($iIdTenant);

		return $oTenant ? $oTenant : null;
	}
	
	
	public function GetDefaultGlobalTenant()
	{
		$oTenant = $this->oApiTenantsManager->getDefaultGlobalTenant();
		
		return $oTenant ? $oTenant : null;
	}
	
	public function GetTenantName()
	{
		$sTenant = '';
		$sAuthToken = $this->oHttp->GetPost('AuthToken', '');
		if (!empty($sAuthToken))
		{
			$iUserId = \CApi::getAuthenticatedUserId($sAuthToken);
			if ($iUserId !== false && $iUserId > 0)
			{
				$oUser = $this->GetUser($iUserId);
				if ($oUser)
				{
					$oTenant = $this->GetTenantById($oUser->IdTenant);
					if ($oTenant)
					{
						$sTenant = $oTenant->Name;
					}
				}
			}
			$sPostTenant = $this->oHttp->GetPost('TenantName', '');
			if (!empty($sPostTenant) && !empty($sTenant) && $sPostTenant !== $sTenant)
			{
				$sTenant = '';
			}
		}
		else
		{
			$sTenant = $this->oHttp->GetRequest('tenant', '');
		}
		\CApi::setTenantName($sTenant);
		return $sTenant;
	}
	
	public function Logout()
	{	
		$mAuthToken = \CApi::getAuthenticatedUserAuthToken();
		if ($mAuthToken !== false)
		{
			\CApi::UserSession()->Delete($mAuthToken);
		}
		else
		{
			throw new \System\Exceptions\ClientException(\Auth\Notifications::IncorrentAuthToken);
		}

		return true;
	}
	
	protected function verifyAdminAccess()
	{
		$oUser = \CApi::getAuthenticatedUser();
		if (empty($oUser) || $oUser->Role !== 0)
		{
			throw new \System\Exceptions\ClientException(\System\Notifications::AccessDenied);
		}
	}
}
