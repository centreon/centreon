export interface GetLabel {
  unsetLabel: string;
  setLabel: string;
  asFavorite: boolean;
}

export interface FavoriteEndpoint {
  dashboardId?: number;
}

export interface Refetch {
  refetch?: () => void;
}
