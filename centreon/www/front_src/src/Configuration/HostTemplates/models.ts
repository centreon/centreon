import type { Icon, NamedEntity } from '../Hosts/models';

export interface HostTemplateListItem extends NamedEntity {
  alias?: string | null;
  icon?: Icon | null;
  isLocked: boolean;
  serviceTemplatesCount?: number | null;
  // The parent templates, in the order they are inherited from.
  templates: Array<NamedEntity>;
}

export type Filters = {
  name: string;
  isLockedShown: boolean;
};
