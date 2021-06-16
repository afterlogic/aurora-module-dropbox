import settings from '../../Dropbox/vue/settings'

export default {
  name: 'DropboxWebclient',
  init (appData) {
    settings.init(appData)
  },
  getAdminSystemTabs () {
    return [
      {
        name: 'dropbox',
        title: 'DROPBOX.LABEL_SETTINGS_TAB',
        component () {
          return import('src/../../../Dropbox/vue/components/DropboxAdminSettings')
        },
      },
    ]
  },
}
