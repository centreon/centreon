import type { Entity, Extensions } from '../models';

const getModule = (id: string): Entity => ({
  description: `Centreon ${id}`,
  id,
  images: [],
  is_internal: false,
  label: 'Centreon',
  last_update: '2026-01-01',
  license: { expiration_date: '2099-12-31T00:00:00Z', required: true },
  release_note: '',
  stability: 'stable',
  title: id,
  type: 'module',
  version: {
    available: '26.10.1',
    current: '26.10.0',
    installed: true,
    outdated: true
  }
});

export const outdatedModuleIds = [
  'centreon-bam-server',
  'centreon-map4-web-client'
];

export const getListResponse = (): { result: Extensions; status: boolean } => ({
  result: {
    module: { entities: outdatedModuleIds.map(getModule) },
    widget: { entities: [] }
  },
  status: true
});

export const getUpdatedResponse = (
  id: string
): { result: { entity: Entity; message: null }; status: boolean } => ({
  result: { entity: getModule(id), message: null },
  status: true
});

export const clearedCacheResponse = { result: null, status: true };

export const getRefusedResponse = (
  message: string
): { result: { entity: null; message: string }; status: boolean } => ({
  result: { entity: null, message },
  status: false
});
