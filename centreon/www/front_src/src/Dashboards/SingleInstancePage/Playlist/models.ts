import { Dashboard } from '../../components/DashboardPlaylists/models';

interface Share {
  id: number;
  name: string;
  role: string;
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
  ownRole: 'viewer' | 'editor';
  publicLink: string | null;
  shares: {
    contactgroups: Array<Share>;
    contacts: Array<Share>;
  };
  updateAt: string;
}
