<?php

class DropBoxModule extends AApiModule
{
	protected static $sService = 'dropbox';
	
	protected $aRequireModules = array(
		'OAuthIntegratorWebclient', 'DropBoxAuthWebclient'
	);
	
	public function init() 
	{
		$this->incClass('Dropbox/autoload');
		
		$this->subscribeEvent('Files::GetStorages::after', array($this, 'GetStorages'));
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'));
		$this->subscribeEvent('Files::GetFiles::after', array($this, 'GetFiles'));
		$this->subscribeEvent('Files::FileExists::after', array($this, 'FileExists'));
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'GetFileInfo'));
		$this->subscribeEvent('Files::GetFile::after', array($this, 'GetFile'));
		$this->subscribeEvent('Files::CreateFolder::after', array($this, 'CreateFolder'));
		$this->subscribeEvent('Files::CreateFile::after', array($this, 'CreateFile'));
		$this->subscribeEvent('Files::CreatePublicLink::after', array($this, 'CreatePublicLink'));
		$this->subscribeEvent('Files::DeletePublicLink::after', array($this, 'DeletePublicLink'));
		$this->subscribeEvent('Files::Delete::after', array($this, 'Delete'));
		$this->subscribeEvent('Files::Rename::after', array($this, 'Rename'));
		$this->subscribeEvent('Files::Move::after', array($this, 'Move'));
		$this->subscribeEvent('Files::Copy::after', array($this, 'Copy')); 
		
/*
		$this->subscribeEvent('OAuthIntegratorAction', array($this, 'onOAuthIntegratorAction'));
		$this->subscribeEvent('GetServices', array($this, 'onGetServices'));
		$this->subscribeEvent('GetServicesSettings', array($this, 'onGetServicesSettings'));
		$this->subscribeEvent('UpdateServicesSettings', array($this, 'onUpdateServicesSettings'));
 */
	}
	
	public function GetStorages(&$aResult)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oOAuthIntegratorWebclientModule = \CApi::GetModuleDecorator('OAuthIntegratorWebclient');
		$oSocialAccount = $oOAuthIntegratorWebclientModule->GetAccount(self::$sService);

		if ($oSocialAccount instanceof COAuthAccount && $oSocialAccount->Type === self::$sService)
		{		
			$aResult['@Result'][] = [
				'Type' => self::$sService, 
				'IsExternal' => true,
				'DisplayName' => 'DropBox'
			];
		}
	}
	
	protected function GetClient($sType)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		if ($sType === self::$sService)
		{
			$oOAuthIntegratorWebclientModule = \CApi::GetModuleDecorator('OAuthIntegratorWebclient');
			$oSocialAccount = $oOAuthIntegratorWebclientModule->GetAccount($sType);
			if ($oSocialAccount)
			{
				$mResult = new \Dropbox\Client($oSocialAccount->AccessToken, "Aurora App");
			}
		}
		
		return $mResult;
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function FileExists(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = false;

				if ($oClient->getMetadata('/'.ltrim($aData['Type'], '/').'/'.$aData['Name']))
				{
					$bResult = true;
				}
				$aData['@Result'] = $bResult;
			}
		}
	}	

	protected function _dirname($sPath)
	{
		$sPath = dirname($sPath);
		return str_replace(DIRECTORY_SEPARATOR, '/', $sPath); 
	}
	
	protected function _basename($sPath)
	{
		$aPath = explode('/', $sPath);
		return end($aPath); 
	}

	/**
	 * @param array $aData
	 */
	protected function PopulateFileInfo($sType, $oClient, $aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->_dirname($aData['path']), '/');
			
//			$oSocial = $this->GetSocial($oAccount);
			$mResult /*@var $mResult \CFileStorageItem */ = new  \CFileStorageItem();
			$mResult->IsExternal = true;
			$mResult->TypeStr = $sType;
			$mResult->IsFolder = $aData['is_dir'];
			$mResult->Id = $this->_basename($aData['path']);
			$mResult->Name = $mResult->Id;
			$mResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$mResult->Size = $aData['bytes'];
//			$bResult->Owner = $oSocial->Name;
			$mResult->LastModified = date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$mResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$mResult->FullPath = $mResult->Name !== '' ? $mResult->Path . '/' . $mResult->Name : $mResult->Path ;
			
			$mResult->Hash = \CApi::EncodeKeyValues(array(
				'Type' => $sType,
				'Path' => $mResult->Path,
				'Name' => $mResult->Name,
				'Size' => $mResult->Size
			));

/*			
			if (!$mResult->IsFolder && $aData['thumb_exists'])
			{
				$mResult->Thumb = true;
				$aThumb = $oClient->getThumbnail($aData['path'], "png", "m");
				if ($aThumb && isset($aThumb[1]))
				{
					$mResult->ThumbnailLink = "data:image/png;base64," . base64_encode($aThumb[1]);
				}
			}
*/
			
		}
		return $mResult;
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFileInfo($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			$aData = $oClient->getMetadata('/'.ltrim($sPath, '/').'/'.$sName);
			$bResult = $this->PopulateFileInfo($oAccount, $sType, $oClient, $aData);
		}
	}	
	
	/**
	 */
	public function onGetFile($Type, $Path, $Name, &$Result)
	{
		if ($Type === self::$sService)
		{
			$oClient = $this->GetClient($Type);
			if ($oClient)
			{
				$Result = fopen('php://memory','wb+');
				$oClient->getFile('/'.ltrim($Path, '/').'/'.$Name, $Result);
				rewind($Result);
			}
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFiles(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if ($aData['Type'] === self::$sService)
		{
			$mResult = array();
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$aItems = array();
				$Path = '/'.ltrim($aData['Path'], '/');
				if (empty($aData['Pattern']))
				{
					$aItem = $oClient->getMetadataWithChildren($Path);
					$aItems = $aItem['contents'];
				}
				else
				{
					$aItems = $oClient->searchFileNames($aData['Path'], $aData['Pattern']);
				}

				foreach($aItems as $aChild) 
				{
					$oItem /*@var $oItem \CFileStorageItem */ = $this->PopulateFileInfo($aData['Type'], $oClient, $aChild);
					if ($oItem)
					{
						$mResult[] = $oItem;
					}
				}				
			}

			$aData['@Result']['Items'] = $mResult;
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function CreateFolder(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = false;

				if ($oClient->createFolder('/'.ltrim($aData['Path'], '/').'/'.$aData['FolderName']) !== null)
				{
					$bResult = true;
				}
				
				$aData['@Result'] = $bResult;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function CreateFile(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = false;

				$sPath = '/'.ltrim($sPath, '/').'/'.$aData['FileName'];
				if (is_resource($aData['Data']))
				{
					if ($oClient->uploadFile($sPath, \Dropbox\WriteMode::add(), $aData['Data']))
					{
						$bResult = true;
					}
				}
				else
				{
					if ($oClient->uploadFileFromString($sPath, \Dropbox\WriteMode::add(), $aData['Data']))
					{
						$bResult = true;
					}
				}
				
				$aData['@Result'] = $bResult;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Delete(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = false;

				foreach ($aData['Items'] as $aItem)
				{
					$oClient->delete('/'.ltrim($aItem['Path'], '/').'/'.$aItem['Name']);
					$bResult = true;
				}

				$aData['@Result'] = $bResult;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Rename(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = false;

				$sPath = ltrim($aData['Path'], '/');
				if ($oClient->move('/'.$sPath.'/'.$aData['Name'], '/'.$sPath.'/'.$aData['NewName']))
				{
					$bResult = true;
				}
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Move(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['FromType'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['FromType']);
			if ($oClient)
			{
				$bResult = false;

				if ($aData['ToType'] === $aData['FromType'])
				{
					foreach ($aData['Files'] as $aFile)
					{
						$oClient->move('/'.ltrim($aData['FromPath'], '/').'/'.$aFile['Name'], '/'.ltrim($aData['ToPath'], '/').'/'.$aFile['Name']);
					}
					$bResult = true;
				}
				
				$aData['@Result'] = $bResult;
			}
		}
	}	

	/**
	 * @param \CAccount $oAccount
	 */
	public function Copy(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['FromType'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['FromType']);
			if ($oClient)
			{
				$bResult = false;

				if ($aData['ToType'] === $aData['FromType'])
				{
					foreach ($aData['Files'] as $aFile)
					{
						$oClient->copy('/'.ltrim($aData['FromPath'], '/').'/'.$aFile['Name'], '/'.ltrim($aData['ToPath'], '/').'/'.$aFile['Name']);
					}
					$bResult = true;
				}
				
				$aData['@Result'] = $bResult;
			}
		}
	}		
	
	
}
