<<<<<<< HEAD
import { RefObject, useEffect, useRef } from 'react';

import { isNil, isEmpty, pipe, not, defaultTo, propEq, findIndex } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { useTheme, alpha, Skeleton } from '@mui/material';

import { MemoizedPanel as Panel, Tab } from '@centreon/ui';

=======
import * as React from 'react';

import {
  isNil,
  isEmpty,
  pipe,
  not,
  defaultTo,
  propEq,
  findIndex,
  pick,
} from 'ramda';
import { useTranslation } from 'react-i18next';

import { useTheme, alpha } from '@material-ui/core';
import { Skeleton } from '@material-ui/lab';

import { MemoizedPanel as Panel, Tab } from '@centreon/ui';

import { useResourceContext } from '../Context';
>>>>>>> centreon/dev-21.10.x
import { rowColorConditions } from '../colors';

import Header from './Header';
import { ResourceDetails } from './models';
import { TabById, detailsTabId, tabs } from './tabs';
import { Tab as TabModel, TabId } from './tabs/models';
<<<<<<< HEAD
import {
  clearSelectedResourceDerivedAtom,
  detailsAtom,
  openDetailsTabIdAtom,
  panelWidthStorageAtom,
  selectResourceDerivedAtom,
} from './detailsAtoms';
=======
>>>>>>> centreon/dev-21.10.x

export interface DetailsSectionProps {
  details?: ResourceDetails;
}

<<<<<<< HEAD
=======
export interface TabBounds {
  bottom: number;
  top: number;
}

const Context = React.createContext<TabBounds>({
  bottom: 0,
  top: 0,
});

>>>>>>> centreon/dev-21.10.x
const Details = (): JSX.Element | null => {
  const { t } = useTranslation();
  const theme = useTheme();

<<<<<<< HEAD
  const panelRef = useRef<HTMLDivElement>();

  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);
  const [openDetailsTabId, setOpenDetailsTabId] = useAtom(openDetailsTabIdAtom);
  const details = useAtomValue(detailsAtom);
  const clearSelectedResource = useUpdateAtom(clearSelectedResourceDerivedAtom);
  const selectResource = useUpdateAtom(selectResourceDerivedAtom);

  useEffect(() => {
=======
  const panelRef = React.useRef<HTMLDivElement>();

  const {
    openDetailsTabId,
    details,
    panelWidth,
    setOpenDetailsTabId,
    clearSelectedResource,
    setPanelWidth,
    selectResource,
  } = useResourceContext();

  React.useEffect(() => {
>>>>>>> centreon/dev-21.10.x
    if (isNil(details)) {
      return;
    }

    const isOpenTabActive = tabs
      .find(propEq('id', openDetailsTabId))
      ?.getIsActive(details);

    if (!isOpenTabActive) {
      setOpenDetailsTabId(detailsTabId);
    }
  }, [details]);

  const getVisibleTabs = (): Array<TabModel> => {
    if (isNil(details)) {
      return tabs;
    }

    return tabs.filter(({ getIsActive }) => getIsActive(details));
  };

  const getTabIndex = (tabId: TabId): number => {
    const index = findIndex(propEq('id', tabId), getVisibleTabs());

    return index > 0 ? index : 0;
  };

  const changeSelectedTabId = (tabId: TabId) => (): void => {
    setOpenDetailsTabId(tabId);
  };

  const getHeaderBackgroundColor = (): string | undefined => {
    const { downtimes, acknowledgement } = details || {};

    const foundColorCondition = rowColorConditions(theme).find(
      ({ condition }) =>
        condition({
          acknowledged: !isNil(acknowledgement),
          in_downtime: pipe(defaultTo([]), isEmpty, not)(downtimes),
        }),
    );

    if (isNil(foundColorCondition)) {
<<<<<<< HEAD
      return theme.palette.background.paper;
=======
      return theme.palette.common.white;
>>>>>>> centreon/dev-21.10.x
    }

    return alpha(foundColorCondition.color, 0.8);
  };

  return (
<<<<<<< HEAD
    <Panel
      header={<Header details={details} onSelectParent={selectResource} />}
      headerBackgroundColor={getHeaderBackgroundColor()}
      memoProps={[openDetailsTabId, details, panelWidth]}
      ref={panelRef as RefObject<HTMLDivElement>}
      selectedTab={<TabById details={details} id={openDetailsTabId} />}
      selectedTabId={getTabIndex(openDetailsTabId)}
      tabs={getVisibleTabs().map(({ id, title }) => (
        <Tab
          aria-label={t(title)}
          data-testid={id}
          disabled={isNil(details)}
          key={id}
          label={isNil(details) ? <Skeleton width={60} /> : t(title)}
          onClick={changeSelectedTabId(id)}
        />
      ))}
      width={panelWidth}
      onClose={clearSelectedResource}
      onResize={setPanelWidth}
    />
=======
    <Context.Provider
      value={pick(
        ['top', 'bottom'],
        panelRef.current?.getBoundingClientRect() || { bottom: 0, top: 0 },
      )}
    >
      <Panel
        header={<Header details={details} onSelectParent={selectResource} />}
        headerBackgroundColor={getHeaderBackgroundColor()}
        memoProps={[openDetailsTabId, details, panelWidth]}
        ref={panelRef as React.RefObject<HTMLDivElement>}
        selectedTab={<TabById details={details} id={openDetailsTabId} />}
        selectedTabId={getTabIndex(openDetailsTabId)}
        tabs={getVisibleTabs().map(({ id, title }) => (
          <Tab
            aria-label={t(title)}
            data-testid={id}
            disabled={isNil(details)}
            key={id}
            label={isNil(details) ? <Skeleton width={60} /> : t(title)}
            onClick={changeSelectedTabId(id)}
          />
        ))}
        width={panelWidth}
        onClose={clearSelectedResource}
        onResize={setPanelWidth}
      />
    </Context.Provider>
>>>>>>> centreon/dev-21.10.x
  );
};

export default Details;
<<<<<<< HEAD
=======
export { Context as TabContext };
>>>>>>> centreon/dev-21.10.x
