export interface NamedEntity {
  id: number;
  name: string;
}

export interface Icon extends NamedEntity {
  url: string;
}

export interface HostTemplateListItem extends NamedEntity {
  alias?: string | null;
  // The template's own icon, else the first one inherited through its parents.
  icon?: Icon | null;
  isLocked: boolean;
  linkedServiceTemplatesCount: number;
  parentTemplates: Array<NamedEntity>;
}

// The keys are the query parameters `useLoadData` sends them as.
export interface Filters {
  name: string;
  is_locked: boolean;
}
