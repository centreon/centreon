import { Dashboard } from '../../components/DashboardPlaylists/models';

interface Share {
  id: number;
  name: string;
  role: string;
}

export enum OwnRole {
  Editor = 'editor',
  Viewer = 'viewer'
}

export interface Playlist {
  author: {
    id: number;
    name: string;
  };
  createdAt: string;
  dashboards: Array<Dashboard>;
  description: string | null;
  id: number;
  isPublic: boolean;
  name: string;
  ownRole: OwnRole;
  publicLink: string | null;
  rotationTime: number;
  shares: {
    contactgroups: Array<Share>;
    contacts: Array<Share>;
  };
  updatedAt: string;
}
