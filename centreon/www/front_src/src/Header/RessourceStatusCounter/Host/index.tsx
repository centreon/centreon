/* eslint-disable @typescript-eslint/naming-convention */
<<<<<<< HEAD

import clsx from 'clsx';
import * as yup from 'yup';
import numeral from 'numeral';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation, withTranslation } from 'react-i18next';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import HostIcon from '@mui/icons-material/Dns';
=======
import React from 'react';

import classnames from 'classnames';
import * as yup from 'yup';
import numeral from 'numeral';
import { Link } from 'react-router-dom';
import { useTranslation, withTranslation } from 'react-i18next';

import HostIcon from '@material-ui/icons/Dns';
>>>>>>> centreon/dev-21.10.x

import {
  IconHeader,
  IconToggleSubmenu,
  SubmenuHeader,
  SubmenuItem,
  SubmenuItems,
  SeverityCode,
  StatusCounter,
<<<<<<< HEAD
  SelectEntry,
} from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { Criteria } from '../../../Resources/Filter/Criterias/models';
import { applyFilterDerivedAtom } from '../../../Resources/Filter/filterAtoms';
=======
} from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';

import styles from '../../header.scss';
>>>>>>> centreon/dev-21.10.x
import {
  getHostResourcesUrl,
  downCriterias,
  unreachableCriterias,
  upCriterias,
  pendingCriterias,
  unhandledStateCriterias,
<<<<<<< HEAD
  hostCriterias,
} from '../getResourcesUrl';
import RessourceStatusCounter, { useStyles } from '..';
import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
=======
} from '../getResourcesUrl';
import RessourceStatusCounter, { useStyles } from '..';
>>>>>>> centreon/dev-21.10.x

const hostStatusEndpoint =
  'internal.php?object=centreon_topcounter&action=hosts_status';

const numberFormat = yup.number().required().integer();

const statusSchema = yup.object().shape({
  down: yup.object().shape({
    total: numberFormat,
    unhandled: numberFormat,
  }),
  ok: numberFormat,
  pending: numberFormat,
  refreshTime: numberFormat,
  total: numberFormat,
  unreachable: yup.object().shape({
    total: numberFormat,
    unhandled: numberFormat,
  }),
});

interface HostData {
  down: {
    total: number;
    unhandled: number;
  };
  ok: number;
  pending: number;
  total: number;
  unandled: number;
  unreachable: {
    total: number;
    unhandled: number;
  };
}

<<<<<<< HEAD
interface SelectResourceProps {
  criterias: Array<Criteria>;
  link: string;
  toggle?: () => void;
}

const HostStatusCounter = (): JSX.Element => {
  const classes = useStyles();
  const navigate = useNavigate();

  const { t } = useTranslation();

  const { use_deprecated_pages } = useAtomValue(userAtom);
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);

  const unhandledDownHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: downCriterias.value as Array<SelectEntry>,
  });
=======
const HostStatusCounter = (): JSX.Element => {
  const classes = useStyles();

  const { t } = useTranslation();

  const { use_deprecated_pages } = useUserContext();

>>>>>>> centreon/dev-21.10.x
  const unhandledDownHostsLink = use_deprecated_pages
    ? '/main.php?p=20202&o=h_down&search='
    : getHostResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: downCriterias,
      });

<<<<<<< HEAD
  const unhandledUnreachableHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    states: unhandledStateCriterias.value,
    statuses: unreachableCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const unhandledUnreachableHostsLink = use_deprecated_pages
    ? '/main.php?p=20202&o=h_unreachable&search='
    : getHostResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: unreachableCriterias,
      });

<<<<<<< HEAD
  const upHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    statuses: upCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const upHostsLink = use_deprecated_pages
    ? '/main.php?p=20202&o=h_up&search='
    : getHostResourcesUrl({
        statusCriterias: upCriterias,
      });

<<<<<<< HEAD
  const hostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const hostsLink = use_deprecated_pages
    ? '/main.php?p=20202&o=h&search='
    : getHostResourcesUrl();

<<<<<<< HEAD
  const pendingHostsCriterias = getDefaultCriterias({
    resourceTypes: hostCriterias.value,
    statuses: pendingCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const pendingHostsLink = use_deprecated_pages
    ? '/main.php?p=20202&o=h_pending&search='
    : getHostResourcesUrl({
        statusCriterias: pendingCriterias,
      });

<<<<<<< HEAD
  const changeFilterAndNavigate =
    ({ link, criterias, toggle }: SelectResourceProps) =>
    (e): void => {
      e.preventDefault();
      toggle?.();
      if (!use_deprecated_pages) {
        applyFilter({ criterias, id: '', name: 'New Filter' });
      }
      navigate(link);
    };

=======
>>>>>>> centreon/dev-21.10.x
  return (
    <RessourceStatusCounter<HostData>
      endpoint={hostStatusEndpoint}
      loaderWidth={27}
      schema={statusSchema}
    >
      {({ hasPending, toggled, toggleDetailedView, data }): JSX.Element => (
<<<<<<< HEAD
        <div>
=======
        <div className={`${styles.wrapper} wrap-right-hosts`}>
>>>>>>> centreon/dev-21.10.x
          <SubmenuHeader active={toggled}>
            <IconHeader
              Icon={HostIcon}
              iconName={t('Hosts')}
              pending={hasPending}
              onClick={toggleDetailedView}
            />
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Hosts Down"
              to={unhandledDownHostsLink}
              onClick={changeFilterAndNavigate({
                criterias: unhandledDownHostsCriterias,
                link: unhandledDownHostsLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Hosts Down"
              to={unhandledDownHostsLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter
                count={data.down.unhandled}
                severityCode={SeverityCode.High}
              />
            </Link>
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Hosts Unreachable"
              to={unhandledUnreachableHostsLink}
              onClick={changeFilterAndNavigate({
                criterias: unhandledUnreachableHostsCriterias,
                link: unhandledUnreachableHostsLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Hosts Unreachable"
              to={unhandledUnreachableHostsLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter
                count={data.unreachable.unhandled}
                severityCode={SeverityCode.Low}
              />
            </Link>
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Hosts Up"
              to={upHostsLink}
              onClick={changeFilterAndNavigate({
                criterias: upHostsCriterias,
                link: upHostsLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Hosts Up"
              to={upHostsLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter count={data.ok} severityCode={SeverityCode.Ok} />
            </Link>
            <IconToggleSubmenu
              data-testid="submenu-hosts"
              iconType="arrow"
              rotate={toggled}
              onClick={toggleDetailedView}
            />
            <div
<<<<<<< HEAD
              className={clsx(classes.subMenuToggle, {
                [classes.subMenuToggleActive]: toggled,
=======
              className={classnames(styles['submenu-toggle'], {
                [styles['submenu-toggle-active'] as string]: toggled,
>>>>>>> centreon/dev-21.10.x
              })}
            >
              <SubmenuItems>
                <Link
                  className={classes.link}
                  to={hostsLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: hostsCriterias,
                    link: hostsLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu hosts count all"
                    submenuCount={numeral(data.total).format()}
                    submenuTitle={t('All')}
                    titleTestId="submenu hosts title all"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={unhandledDownHostsLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: unhandledDownHostsCriterias,
                    link: unhandledDownHostsLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu hosts count down"
                    dotColored="red"
                    submenuCount={`${numeral(data.down.unhandled).format(
                      '0a',
                    )}/${numeral(data.down.total).format('0a')}`}
                    submenuTitle={t('Down')}
                    titleTestId="submenu hosts title down"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={unhandledUnreachableHostsLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: unhandledUnreachableHostsCriterias,
                    link: unhandledUnreachableHostsLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu hosts count unreachable"
                    dotColored="gray"
                    submenuCount={`${numeral(data.unreachable.unhandled).format(
                      '0a',
                    )}/${numeral(data.unreachable.total).format('0a')}`}
                    submenuTitle={t('Unreachable')}
                    titleTestId="submenu hosts title unreachable"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={upHostsLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: upHostsCriterias,
                    link: upHostsLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu hosts count ok"
                    dotColored="green"
                    submenuCount={numeral(data.ok).format()}
                    submenuTitle={t('Up')}
                    titleTestId="submenu hosts title ok"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={pendingHostsLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: pendingHostsCriterias,
                    link: pendingHostsLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu hosts count pending"
                    dotColored="blue"
                    submenuCount={numeral(data.pending).format()}
                    submenuTitle={t('Pending')}
                    titleTestId="submenu hosts title pending"
                  />
                </Link>
              </SubmenuItems>
            </div>
          </SubmenuHeader>
        </div>
      )}
    </RessourceStatusCounter>
  );
};

export default withTranslation()(HostStatusCounter);
