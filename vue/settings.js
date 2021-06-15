import _ from 'lodash'

import typesUtils from 'src/utils/types'

class DropboxSettings {
    constructor(appData) {
        const dropboxWebclientData = typesUtils.pObject(appData.Dropbox)
        if (!_.isEmpty(dropboxWebclientData)) {
            this.displayName = dropboxWebclientData.DisplayName
            this.enableModule = dropboxWebclientData.EnableModule
            this.id = dropboxWebclientData.Id
            this.name = dropboxWebclientData.Name
            this.scopes = dropboxWebclientData.Scopes
            this.secret = dropboxWebclientData.Secret
        }
    }
    
    saveDropboxSettings({ EnableModule, Id, Scopes, Secret}) {
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
    saveDropboxSettings(data) {
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
