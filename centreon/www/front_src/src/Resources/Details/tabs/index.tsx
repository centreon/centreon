<<<<<<< HEAD
import { lazy, Suspense } from 'react';

import { isNil, find, propEq, invertObj, path, equals } from 'ramda';

import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { isNil, find, propEq, invertObj, path, equals } from 'ramda';

import { makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import {
  labelDetails,
  labelGraph,
  labelTimeline,
  labelServices,
  labelMetrics,
<<<<<<< HEAD
  labelNotification,
} from '../../translatedLabels';
import { ResourceDetails } from '../models';
import DetailsLoadingSkeleton from '../LoadingSkeleton';

import { Tab, TabId } from './models';

const DetailsTab = lazy(() => import('./Details'));
const GraphTab = lazy(() => import('./Graph'));
const TimelineTab = lazy(() => import('./Timeline'));
const ServicesTab = lazy(() => import('./Services'));
const MetricsTab = lazy(() => import('./Metrics'));
const NotificationsTab = lazy(() => import('./Notifications'));
=======
} from '../../translatedLabels';
import { ResourceDetails } from '../models';

import DetailsTab from './Details';
import GraphTab from './Graph';
import { Tab, TabId } from './models';
import TimelineTab from './Timeline';
import ServicesTab from './Services';
import MetricsTab from './Metrics';
>>>>>>> centreon/dev-21.10.x

const detailsTabId = 0;
const servicesTabId = 1;
const timelineTabId = 2;
const graphTabId = 3;
const metricsTabId = 4;
<<<<<<< HEAD
const notificationsTabId = 5;
=======
>>>>>>> centreon/dev-21.10.x

export interface TabProps {
  details?: ResourceDetails;
}

const tabs: Array<Tab> = [
  {
    Component: DetailsTab,
    ariaLabel: labelDetails,
    getIsActive: (): boolean => true,
    id: detailsTabId,
    title: labelDetails,
  },
  {
    Component: ServicesTab,
    getIsActive: (details: ResourceDetails): boolean => {
      return details.type === 'host';
    },
    id: servicesTabId,
    title: labelServices,
  },
  {
    Component: TimelineTab,
    getIsActive: (): boolean => true,
    id: timelineTabId,
    title: labelTimeline,
  },
  {
    Component: GraphTab,
    getIsActive: (details: ResourceDetails): boolean => {
      if (isNil(details)) {
        return false;
      }

      if (equals(details.type, 'host')) {
        return true;
      }

      return !isNil(path(['links', 'endpoints', 'performance_graph'], details));
    },
    id: graphTabId,
    title: labelGraph,
  },
  {
    Component: MetricsTab,
    getIsActive: (details: ResourceDetails): boolean => {
      if (isNil(details)) {
        return false;
      }

      return details.type === 'metaservice';
    },
    id: metricsTabId,
    title: labelMetrics,
  },
<<<<<<< HEAD
  {
    Component: NotificationsTab,
    ariaLabel: labelNotification,
    getIsActive: (): boolean => true,
    id: notificationsTabId,
    title: labelNotification,
  },
=======
>>>>>>> centreon/dev-21.10.x
];

const useStyles = makeStyles((theme) => ({
  container: {
    padding: theme.spacing(2),
  },
}));

interface TabByIdProps {
  details?: ResourceDetails;
  id: number;
}

const TabById = ({ id, details }: TabByIdProps): JSX.Element | null => {
  const classes = useStyles();

  const { Component } = find(propEq('id', id), tabs) as Tab;

  return (
    <div className={classes.container}>
<<<<<<< HEAD
      <Suspense fallback={<DetailsLoadingSkeleton />}>
        <Component details={details} />
      </Suspense>
=======
      <Component details={details} />
>>>>>>> centreon/dev-21.10.x
    </div>
  );
};

const tabIdByLabel = {
  details: detailsTabId,
  graph: graphTabId,
  metrics: metricsTabId,
<<<<<<< HEAD
  notification: notificationsTabId,
=======
>>>>>>> centreon/dev-21.10.x
  services: servicesTabId,
  timeline: timelineTabId,
};

const getTabIdFromLabel = (label: string): TabId => {
  const tabId = tabIdByLabel[label];

  if (isNil(tabId)) {
    return detailsTabId;
  }

  return tabId;
};

const getTabLabelFromId = (id: TabId): string => {
  return invertObj(tabIdByLabel)[id];
};

export {
  detailsTabId,
  timelineTabId,
  graphTabId,
  servicesTabId,
  metricsTabId,
<<<<<<< HEAD
  notificationsTabId,
=======
>>>>>>> centreon/dev-21.10.x
  tabs,
  TabById,
  getTabIdFromLabel,
  getTabLabelFromId,
};
