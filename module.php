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
		$bResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->_dirname($aData['path']), '/');
			
//			$oSocial = $this->GetSocial($oAccount);
			$bResult /*@var $bResult \CFileStorageItem */ = new  \CFileStorageItem();
			$bResult->IsExternal = true;
			$bResult->TypeStr = $sType;
			$bResult->IsFolder = $aData['is_dir'];
			$bResult->Id = $this->_basename($aData['path']);
			$bResult->Name = $bResult->Id;
			$bResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$bResult->Size = $aData['bytes'];
//			$bResult->Owner = $oSocial->Name;
			$bResult->LastModified = date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$bResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$bResult->FullPath = $bResult->Name !== '' ? $bResult->Path . '/' . $bResult->Name : $bResult->Path ;
			
			$bResult->Hash = \CApi::EncodeKeyValues(array(
				'Type' => $sType,
				'Path' => $bResult->Path,
				'Name' => $bResult->Name,
				'Size' => $bResult->Size
			));
/*				
			if (!$oItem->IsFolder && $aChild['thumb_exists'])
			{
				$oItem->Thumb = true;
				$aThumb = $oClient->getThumbnail($aChild['path'], "png", "m");
				if ($aThumb && isset($aThumb[1]))
				{
					$oItem->ThumbnailLink = "data:image/png;base64," . base64_encode($aThumb[1]);
				}
			}
*/
			
		}
		return $bResult;
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFileInfo($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
	{
		$oClient = $this->GetClient($oAccount, $sType);
		if ($oClient)
		{
			$bBreak = true;
			$aData = $oClient->getMetadata('/'.ltrim($sPath, '/').'/'.$sName);
			$bResult = $this->PopulateFileInfo($oAccount, $sType, $oClient, $aData);
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFile(&$aData)
	{
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->GetClient($aData['Type']);
			if ($oClient)
			{
				$bResult = fopen('php://memory','wb+');
				$oClient->getFile('/'.ltrim($aData['Type'], '/').'/'.$aData['Name'], $bResult);
				rewind($bResult);
				
				$aData['@Result'] = $bResult;
			}
		}
	}	
	
	/**
	 * @param \CAccount $oAccount
	 */
	public function GetFiles(&$aData)
	{
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
