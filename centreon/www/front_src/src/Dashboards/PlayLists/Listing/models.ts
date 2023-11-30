export interface NamedEntity {
  id: number;
  name: string;
}

export interface Dashboard {
  id: number;
  order: number;
}

export interface Contact extends NamedEntity {
  role: string;
}

export interface Share {
  contactgroups?: Array<Contact>;
  contacts?: Array<Contact>;
}

export interface PlaylistType {
  author: NamedEntity;
  createdAt: string;
  dashboards: Array<Dashboard>;
  description?: string;
  id: number;
  isPublic: boolean | null;
  name: string;
  publicLink?: string | null;
  rotationTime: number;
  shares: Share;
  updatedAt?: string;
}

export interface MetaType {
  limit: number;
  page: number;
  search?: Record<string, unknown>;
  sort_by?: Record<string, unknown>;
  total: number;
}

export interface PlaylistListingType {
  meta: MetaType;
  result: Array<PlaylistType>;
}
