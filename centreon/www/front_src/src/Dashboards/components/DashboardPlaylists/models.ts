export interface PlaylistConfig {
  dashboards: Array<{
    id: number;
    order: number;
  }>;
  description: string;
  isPublic: boolean;
  name: string;
  rotationTime: number;
}

export interface PlaylistConfigToAPI {
  dashboards: Array<{
    id: number;
    order: number;
  }>;
  description: string;
  is_public: boolean;
  name: string;
  rotation_time: number;
}
