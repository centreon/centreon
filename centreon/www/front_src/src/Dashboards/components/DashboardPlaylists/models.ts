export interface Dashboard {
  id: number;
  name?: string;
  order: number;
}

export interface Playlist {
  dashboards: Array<{
    id: number;
    order: number;
  }>;
  description: string | null;
  id: number;
  isPublic: boolean;
  name: string;
  rotationTime: number;
}

export interface PlaylistConfig {
  dashboards: Array<Dashboard>;
  description: string | null;
  id?: number;
  isPublic: boolean;
  name: string;
  rotationTime: number;
}

export interface PlaylistConfigToAPI {
  dashboards: Array<{
    id: number;
    order: number;
  }>;
  description: string | null;
  is_public: boolean;
  name: string;
  rotation_time: number;
}
