/* eslint-disable @typescript-eslint/naming-convention */

<<<<<<< HEAD
import clsx from 'clsx';
import * as yup from 'yup';
import numeral from 'numeral';
import { Link, useNavigate } from 'react-router-dom';
import { useTranslation, withTranslation } from 'react-i18next';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import ServiceIcon from '@mui/icons-material/Grain';
=======
import React from 'react';

import classnames from 'classnames';
import * as yup from 'yup';
import numeral from 'numeral';
import { Link } from 'react-router-dom';
import { useTranslation, withTranslation } from 'react-i18next';

import ServiceIcon from '@material-ui/icons/Grain';
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

import getDefaultCriterias from '../../../Resources/Filter/Criterias/default';
import { applyFilterDerivedAtom } from '../../../Resources/Filter/filterAtoms';
=======
} from '@centreon/ui';
import { useUserContext } from '@centreon/ui-context';

import styles from '../../header.scss';
>>>>>>> centreon/dev-21.10.x
import {
  getServiceResourcesUrl,
  criticalCriterias,
  warningCriterias,
  unknownCriterias,
  okCriterias,
  pendingCriterias,
  unhandledStateCriterias,
<<<<<<< HEAD
  serviceCriteria,
} from '../getResourcesUrl';
import RessourceStatusCounter, { useStyles } from '..';
import { Criteria } from '../../../Resources/Filter/Criterias/models';
=======
} from '../getResourcesUrl';
import RessourceStatusCounter, { useStyles } from '..';
>>>>>>> centreon/dev-21.10.x

const serviceStatusEndpoint =
  'internal.php?object=centreon_topcounter&action=servicesStatus';

const numberFormat = yup.number().required().integer();

const statusSchema = yup.object().shape({
  critical: yup.object().shape({
    total: numberFormat,
    unhandled: numberFormat,
  }),
  ok: numberFormat,
  pending: numberFormat,
  refreshTime: numberFormat,
  total: numberFormat,
  unknown: yup.object().shape({
    total: numberFormat,
    unhandled: numberFormat,
  }),
  warning: yup.object().shape({
    total: numberFormat,
    unhandled: numberFormat,
  }),
});

<<<<<<< HEAD
interface SelectResourceProps {
  criterias: Array<Criteria>;
  link: string;
  toggle?: () => void;
}

const ServiceStatusCounter = (): JSX.Element => {
  const classes = useStyles();
  const navigate = useNavigate();

  const { t } = useTranslation();

  const { use_deprecated_pages } = useAtomValue(userAtom);
  const applyFilter = useUpdateAtom(applyFilterDerivedAtom);

  const unhandledCriticalServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: criticalCriterias.value as Array<SelectEntry>,
  });
=======
const ServiceStatusCounter = (): JSX.Element => {
  const classes = useStyles();

  const { t } = useTranslation();

  const { use_deprecated_pages } = useUserContext();

>>>>>>> centreon/dev-21.10.x
  const unhandledCriticalServicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=critical&search='
    : getServiceResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: criticalCriterias,
      });

<<<<<<< HEAD
  const unhandledWarningServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: warningCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const unhandledWarningServicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=warning&search='
    : getServiceResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: warningCriterias,
      });

<<<<<<< HEAD
  const unhandledUnknownServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    states: unhandledStateCriterias.value,
    statuses: unknownCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const unhandledUnknownServicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc_unhandled&statusFilter=unknown&search='
    : getServiceResourcesUrl({
        stateCriterias: unhandledStateCriterias,
        statusCriterias: unknownCriterias,
      });

<<<<<<< HEAD
  const okServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    statuses: okCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const okServicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc&statusFilter=ok&search='
    : getServiceResourcesUrl({ statusCriterias: okCriterias });

<<<<<<< HEAD
  const servicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const servicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc&statusFilter=&search='
    : getServiceResourcesUrl();

<<<<<<< HEAD
  const pendingServicesCriterias = getDefaultCriterias({
    resourceTypes: serviceCriteria.value,
    statuses: pendingCriterias.value as Array<SelectEntry>,
  });
=======
>>>>>>> centreon/dev-21.10.x
  const pendingServicesLink = use_deprecated_pages
    ? '/main.php?p=20201&o=svc&statusFilter=&search='
    : getServiceResourcesUrl({
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
    <RessourceStatusCounter
      endpoint={serviceStatusEndpoint}
      loaderWidth={33}
      schema={statusSchema}
    >
      {({ hasPending, data, toggled, toggleDetailedView }): JSX.Element => (
<<<<<<< HEAD
        <div>
=======
        <div className={`${styles.wrapper} wrap-right-services`}>
>>>>>>> centreon/dev-21.10.x
          <SubmenuHeader active={toggled}>
            <IconHeader
              Icon={ServiceIcon}
              iconName={t('Services')}
              pending={hasPending}
              onClick={toggleDetailedView}
            />
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Services Critical"
              to={unhandledCriticalServicesLink}
              onClick={changeFilterAndNavigate({
                criterias: unhandledCriticalServicesCriterias,
                link: unhandledCriticalServicesLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Services Critical"
              to={unhandledCriticalServicesLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter
                count={data.critical.unhandled}
                severityCode={SeverityCode.High}
              />
            </Link>
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Services Warning"
              to={unhandledWarningServicesLink}
              onClick={changeFilterAndNavigate({
                criterias: unhandledWarningServicesCriterias,
                link: unhandledWarningServicesLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Services Warning"
              to={unhandledWarningServicesLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter
                count={data.warning.unhandled}
                severityCode={SeverityCode.Medium}
              />
            </Link>
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Services Unknown"
              to={unhandledUnknownServicesLink}
              onClick={changeFilterAndNavigate({
                criterias: unhandledUnknownServicesCriterias,
                link: unhandledUnknownServicesLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Services Unknown"
              to={unhandledUnknownServicesLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter
                count={data.unknown.unhandled}
                severityCode={SeverityCode.Low}
              />
            </Link>
            <Link
<<<<<<< HEAD
              className={clsx(classes.link, classes.wrapMiddleIcon)}
              data-testid="Services Ok"
              to={okServicesLink}
              onClick={changeFilterAndNavigate({
                criterias: okServicesCriterias,
                link: okServicesLink,
              })}
=======
              className={classnames(classes.link, styles['wrap-middle-icon'])}
              data-testid="Services Ok"
              to={okServicesLink}
>>>>>>> centreon/dev-21.10.x
            >
              <StatusCounter count={data.ok} severityCode={SeverityCode.Ok} />
            </Link>
            <IconToggleSubmenu
              data-testid="submenu-service"
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
<<<<<<< HEAD
                  data-testid="Services Warning"
                  to={servicesLink}
                  onClick={changeFilterAndNavigate({
                    criterias: servicesCriterias,
                    link: servicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  to={servicesLink}
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu services count all"
                    submenuCount={numeral(data.total).format()}
                    submenuTitle={t('All')}
                    titleTestId="submenu services title all"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={unhandledCriticalServicesLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: unhandledCriticalServicesCriterias,
                    link: unhandledCriticalServicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu services count critical"
                    dotColored="red"
                    submenuCount={`${numeral(
                      data.critical.unhandled,
                    ).format()}/${numeral(data.critical.total).format()}`}
                    submenuTitle={t('Critical')}
                    titleTestId="submenu services title critical"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={unhandledWarningServicesLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: unhandledWarningServicesCriterias,
                    link: unhandledWarningServicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu services count warning"
                    dotColored="orange"
                    submenuCount={`${numeral(
                      data.warning.unhandled,
                    ).format()}/${numeral(data.warning.total).format()}`}
                    submenuTitle={t('Warning')}
                    titleTestId="submenu services title warning"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={unhandledUnknownServicesLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: unhandledUnknownServicesCriterias,
                    link: unhandledUnknownServicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu services count unknown"
                    dotColored="gray"
                    submenuCount={`${numeral(
                      data.unknown.unhandled,
                    ).format()}/${numeral(data.unknown.total).format()}`}
                    submenuTitle={t('Unknown')}
                    titleTestId="submenu services title unknown"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={okServicesLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: okServicesCriterias,
                    link: okServicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    countTestId="submenu services count ok"
                    dotColored="green"
                    submenuCount={numeral(data.ok).format()}
                    submenuTitle={t('Ok')}
                    titleTestId="submenu services title ok"
                  />
                </Link>
                <Link
                  className={classes.link}
                  to={pendingServicesLink}
<<<<<<< HEAD
                  onClick={changeFilterAndNavigate({
                    criterias: pendingServicesCriterias,
                    link: pendingServicesLink,
                    toggle: toggleDetailedView,
                  })}
=======
                  onClick={toggleDetailedView}
>>>>>>> centreon/dev-21.10.x
                >
                  <SubmenuItem
                    dotColored="blue"
                    submenuCount={numeral(data.pending).format()}
                    submenuTitle={t('Pending')}
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

export default withTranslation()(ServiceStatusCounter);
