export enum DashboardLayout {
  Library = 'library',
  Playlist = 'playlists'
}
export enum FavoriteAction {
  add = 0,
  delete = 1
}
export interface GetPath {
  action: FavoriteAction;
  position: number;
}
