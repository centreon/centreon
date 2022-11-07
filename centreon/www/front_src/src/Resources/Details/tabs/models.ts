<<<<<<< HEAD
import { LazyExoticComponent } from 'react';

=======
>>>>>>> centreon/dev-21.10.x
import { ResourceEndpoints } from '../../models';

import { TabProps } from '.';

export type TabEndpoints = Omit<ResourceEndpoints, 'details'>;

<<<<<<< HEAD
export type TabId = 0 | 1 | 2 | 3 | 4 | 5;

export interface Tab {
  Component: LazyExoticComponent<(props: TabProps) => JSX.Element>;
=======
export type TabId = 0 | 1 | 2 | 3 | 4;

export interface Tab {
  Component: (props: TabProps) => JSX.Element;
>>>>>>> centreon/dev-21.10.x
  ariaLabel?: string;
  getIsActive: (details) => boolean;
  id: TabId;
  title: string;
}
