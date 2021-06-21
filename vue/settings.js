import _ from 'lodash'

import typesUtils from 'src/utils/types'

class DropboxSettings {
  constructor (appData) {
    const dropboxWebclientData = typesUtils.pObject(appData.Dropbox)
    if (!_.isEmpty(dropboxWebclientData)) {
      this.displayName = typesUtils.pString(dropboxWebclientData.DisplayName)
      this.enableModule = typesUtils.pBool(dropboxWebclientData.EnableModule)
      this.id = typesUtils.pInt(dropboxWebclientData.Id)
      this.name = typesUtils.pString(dropboxWebclientData.Name)
      this.scopes = typesUtils.pArray(dropboxWebclientData.Scopes)
      this.secret = typesUtils.pString(dropboxWebclientData.Secret)
    }
  }

  saveDropboxSettings ({ EnableModule, Id, Scopes, Secret }) {
    this.enableModule = EnableModule
    this.id = Id
    this.scopes = Scopes
    this.secret = Secret
  }
}

let settings = null

export default {
  init (appData) {
    settings = new DropboxSettings(appData)
  },
  saveDropboxSettings (data) {
    settings.saveDropboxSettings(data)
  },
  getDropboxSettings () {
    return {
      DisplayName: settings.displayName,
      EnableModule: settings.enableModule,
      Id: settings.id,
      Name: settings.name,
      Scopes: settings.scopes,
      Secret: settings.secret
    }
  },
}
