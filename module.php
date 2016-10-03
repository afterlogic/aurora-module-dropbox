<?php
/**
 * @copyright Copyright (c) 2016, Afterlogic Corp.
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
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 * 
 * @package Modules
 */

class DropBoxModule extends AApiModule
{
	protected static $sService = 'dropbox';
	
	protected $aRequireModules = array(
		'OAuthIntegratorWebclient', 'DropBoxAuthWebclient'
	);
	
	/***** private functions *****/
	/**
	 * Initializes DropBox Module.
	 * 
	 * @ignore
	 */
	public function init() 
	{
		$this->incClass('Dropbox/autoload');
		
		$this->subscribeEvent('Files::GetStorages::after', array($this, 'onAfterGetStorages'));
		$this->subscribeEvent('Files::FileExists::after', array($this, 'onAfterFileExists'));
		$this->subscribeEvent('Files::GetFile', array($this, 'onGetFile'));
		$this->subscribeEvent('Files::GetFiles::after', array($this, 'onAfterGetFiles'));
		$this->subscribeEvent('Files::CreateFolder::after', array($this, 'onAfterCreateFolder'));
		$this->subscribeEvent('Files::CreateFile::after', array($this, 'onAfterCreateFile'));
		$this->subscribeEvent('Files::Delete::after', array($this, 'onAfterDelete'));
		$this->subscribeEvent('Files::Rename::after', array($this, 'onAfterRename'));
		$this->subscribeEvent('Files::Move::after', array($this, 'onAfterMove'));
		$this->subscribeEvent('Files::Copy::after', array($this, 'onAfterCopy')); 
		$this->subscribeEvent('Files::GetFileInfo::after', array($this, 'onAfterGetFileInfo'));
	}
	
	/**
	 * Obtaines DropBox client if passed $sType is DropBox account type.
	 * 
	 * @param string $sType Service type.
	 * @return \Dropbox\Client
	 */
	protected function getClient($sType)
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
	 * Write to the $aResult variable information about DropBox storage.
	 * 
	 * @ignore
	 * @param array $aResult Is passed by reference.
	 */
	public function onAfterGetStorages(&$aResult)
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
	
	/**
	 * Writes to $aData['@Result'] **true** if $aData['Type'] is DropBox account type and $aData['Name'] file is exists in DropBox storage.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterFileExists(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->getClient($aData['Type']);
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

	/**
	 * Returns directory name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getDirName($sPath)
	{
		$sPath = dirname($sPath);
		return str_replace(DIRECTORY_SEPARATOR, '/', $sPath); 
	}
	
	/**
	 * Returns base name for the specified path.
	 * 
	 * @param string $sPath Path to the file.
	 * @return string
	 */
	protected function getBaseName($sPath)
	{
		$aPath = explode('/', $sPath);
		return end($aPath); 
	}

	/**
	 * Populates file info.
	 * 
	 * @param string $sType Service type.
	 * @param \Dropbox\Client $oClient DropBox client.
	 * @param array $aData Array contains information about file.
	 * @return \CFileStorageItem|false
	 */
	protected function populateFileInfo($sType, $oClient, $aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		$mResult = false;
		if ($aData && is_array($aData))
		{
			$sPath = ltrim($this->getDirName($aData['path']), '/');
			
//			$oSocial = $this->GetSocial($oAccount);
			$mResult /*@var $mResult \CFileStorageItem */ = new  \CFileStorageItem();
//			$mResult->IsExternal = true;
			$mResult->TypeStr = $sType;
			$mResult->IsFolder = $aData['is_dir'];
			$mResult->Id = $this->getBaseName($aData['path']);
			$mResult->Name = $mResult->Id;
			$mResult->Path = !empty($sPath) ? '/'.$sPath : $sPath;
			$mResult->Size = $aData['bytes'];
//			$bResult->Owner = $oSocial->Name;
			$mResult->LastModified = date_timestamp_get($oClient->parseDateTime($aData['modified']));
			$mResult->Shared = isset($aData['shared']) ? $aData['shared'] : false;
			$mResult->FullPath = $mResult->Name !== '' ? $mResult->Path . '/' . $mResult->Name : $mResult->Path ;

			if (!$mResult->IsFolder && $aData['thumb_exists'])
			{
				$mResult->Thumb = true;
			}
			
		}
		return $mResult;
	}	
	
	/**
	 * Writes to the $mResult variable open file source if $sType is DropBox account type.
	 * 
	 * @ignore
	 * @param int $iUserId Identificator of the authenticated user.
	 * @param string $sType Service type.
	 * @param string $sPath File path.
	 * @param string $sName File name.
	 * @param boolean $bThumb **true** if thumbnail is expected.
	 * @param mixed $mResult
	 */
	public function onGetFile($iUserId, $sType, $sPath, $sName, $bThumb, &$mResult)
	{
		if ($sType === self::$sService)
		{
			$oClient = $this->getClient($sType);
			if ($oClient)
			{
				$mResult = fopen('php://memory','wb+');
				if (!$bThumb)
				{
					$oClient->getFile('/'.ltrim($sPath, '/').'/'.$sName, $mResult);
				}
				else
				{
					$aThumb = $oClient->getThumbnail('/'.ltrim($sPath, '/').'/'.$sName, "png", "m");
					if ($aThumb && isset($aThumb[1]))
					{
						fwrite($mResult, $aThumb[1]);
					}
				}
				rewind($mResult);
			}
		}
	}	
	
	/**
	 * Writes to $aData variable list of DropBox files if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterGetFiles(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::Anonymous);
		
		if ($aData['Type'] === self::$sService)
		{
			$mResult = array();
			$oClient = $this->getClient($aData['Type']);
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
					$oItem /*@var $oItem \CFileStorageItem */ = $this->populateFileInfo($aData['Type'], $oClient, $aChild);
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
	 * Creates folder if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData Is passed by reference.
	 */
	public function onAfterCreateFolder(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->getClient($aData['Type']);
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
	 * Creates file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterCreateFile(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->getClient($aData['Type']);
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
	 * Deletes file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterDelete(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->getClient($aData['Type']);
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
	 * Renames file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterRename(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['Type'] === self::$sService)
		{
			$oClient = $this->getClient($aData['Type']);
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
	 * Moves file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterMove(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['FromType'] === self::$sService)
		{
			$oClient = $this->getClient($aData['FromType']);
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
	 * Copies file if $aData['Type'] is DropBox account type.
	 * 
	 * @ignore
	 * @param array $aData
	 */
	public function onAfterCopy(&$aData)
	{
		\CApi::checkUserRoleIsAtLeast(\EUserRole::NormalUser);
		
		if ($aData['FromType'] === self::$sService)
		{
			$oClient = $this->getClient($aData['FromType']);
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
	
	/**
	 * @ignore
	 * @todo not used
	 * @param object $oAccount
	 * @param string $sType
	 * @param string $sPath
	 * @param string $sName
	 * @param boolean $bResult
	 * @param boolean $bBreak
	 */
	public function onAfterGetFileInfo($oAccount, $sType, $sPath, $sName, &$bResult, &$bBreak)
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
	 * @ignore
	 * @todo not used
	 * @param object $oItem
	 * @return boolean
	 */
	public function onPopulateFileItem(&$oItem)
	{
		if ($oItem->IsLink)
		{
			if (false !== strpos($oItem->LinkUrl, 'dl.dropboxusercontent.com') || 
					false !== strpos($oItem->LinkUrl, 'dropbox.com'))
			{
				$oItem->LinkType = 'dropbox';
				return true;
			}
		}
	}	
	/***** private functions *****/
}
