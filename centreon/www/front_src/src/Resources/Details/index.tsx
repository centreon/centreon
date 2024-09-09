import { RefObject, useEffect, useRef } from 'react';

import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import { equals, findIndex, isNil, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Skeleton, alpha, useTheme } from '@mui/material';

import { MemoizedPanel as Panel, Tab } from '@centreon/ui';
import { featureFlagsDerivedAtom } from '@centreon/ui-context';

import { rowColorConditions } from '../colors';

import Header from './Header';
import {
  clearSelectedResourceDerivedAtom,
  detailsAtom,
  openDetailsTabIdAtom,
  panelWidthStorageAtom,
  selectResourceDerivedAtom
} from './detailsAtoms';
import { ResourceDetails } from './models';
import {
  TabById,
  detailsTabId,
  tabs as initialTabs,
  notificationsTabId
} from './tabs';
import { TabId, Tab as TabModel } from './tabs/models';

export interface DetailsSectionProps {
  details?: ResourceDetails;
}

const Details = (): JSX.Element | null => {
  const { t } = useTranslation();
  const theme = useTheme();

  const panelRef = useRef<HTMLDivElement>();

  const [panelWidth, setPanelWidth] = useAtom(panelWidthStorageAtom);
  const [openDetailsTabId, setOpenDetailsTabId] = useAtom(openDetailsTabIdAtom);
  const featureFlags = useAtomValue(featureFlagsDerivedAtom);
  const details = useAtomValue(detailsAtom);
  const clearSelectedResource = useSetAtom(clearSelectedResourceDerivedAtom);
  const selectResource = useSetAtom(selectResourceDerivedAtom);

  const tabs = featureFlags?.notification
    ? initialTabs.filter((tab) => !equals(tab.id, notificationsTabId))
    : initialTabs;

  useEffect(() => {
    if (isNil(details)) {
      return;
    }

    const isOpenTabActive = tabs
      .find(propEq(openDetailsTabId, 'id'))
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
    const index = findIndex(propEq(tabId, 'id'), getVisibleTabs());

    return index > 0 ? index : 0;
  };

  const changeSelectedTabId = (tabId: TabId) => (): void => {
    setOpenDetailsTabId(tabId);
  };

  const getHeaderBackgroundColor = (): string | undefined => {
    const { is_in_downtime, is_acknowledged } = details || {};

    const foundColorCondition = rowColorConditions(theme).find(
      ({ condition }) =>
        condition({
          acknowledged: is_acknowledged,
          in_downtime: is_in_downtime
        })
    );

    if (isNil(foundColorCondition)) {
      return theme.palette.background.default;
    }

    return alpha(foundColorCondition.color, 0.8);
  };

  return (
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
  );
};

export default Details;
