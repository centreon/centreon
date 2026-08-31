export enum FavoriteAction {
  add = 0,
  delete = 1
}

export enum DashboardLayout {
  Library = 'library',
  Playlist = 'playlists'
}

export interface GetPath {
  action: FavoriteAction;
  position: number;
}
