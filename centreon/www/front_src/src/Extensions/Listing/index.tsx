import { useCallback, useEffect, useState } from 'react';

import { useAtomValue } from 'jotai';
import { filter, find, isEmpty, pathEq, propEq } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import InstallIcon from '@mui/icons-material/Add';
import UpdateIcon from '@mui/icons-material/SystemUpdateAlt';
import { Button } from '@mui/material';
import Stack from '@mui/material/Stack';

import {
  Responsive,
  getData,
  postData,
  useRequest,
  useSnackbar
} from '@centreon/ui';
import type { SelectEntry } from '@centreon/ui';

import usePlatformVersions from '../../Main/usePlatformVersions';
import useNavigation from '../../Navigation/useNavigation';
import FederatedComponents from '../../components/FederatedComponents';
import { appliedFilterCriteriasAtom } from '../Filter/filterAtoms';
import { labelInstallAll, labelUpdateAll } from '../translatedLabels';

import ExtensionDeletePopup from './ExtensionDeleteDialog';
import ExtensionDetailsPopup from './ExtensionDetailsDialog';
import ExtensionsHolder from './ExtensionsHolder';
import { deleteExtension } from './api';
import { buildEndPoint, buildExtensionEndPoint } from './api/endpoint';
import {
  EntityDeleting,
  EntityType,
  ExtensionResult,
  Extensions,
  ExtensionsStatus,
  InstallOrUpdateExtensionResult
} from './models';

const useStyles = makeStyles()((theme) => ({
  contentWrapper: {
    [theme.breakpoints.up(767)]: {
      padding: theme.spacing(1.5, 1.5, 1.5, 0)
    },
    boxSizing: 'border-box',
    margin: theme.spacing(0, 'auto'),
    padding: theme.spacing(1.5, 2.5, 0, 0)
  }
}));

interface Props {
  reloadNavigation: () => void;
}

const scrollMargin = 20;

const ExtensionsManager = ({ reloadNavigation }: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { showErrorMessage, showSuccessMessage } = useSnackbar();

  const [extensions, setExtension] = useState<Extensions>({
    module: {
      entities: []
    },
    widget: {
      entities: []
    }
  });

  const [modulesActive, setModulesActive] = useState(false);
  const [widgetsActive, setWidgetsActive] = useState(false);

  const [entityDetails, setEntityDetails] = useState<EntityType | null>(null);

  const [entityDeleting, setEntityDeleting] = useState<EntityDeleting | null>(
    null
  );

  const [extensionsInstallingStatus, setExtensionsInstallingStatus] =
    useState<ExtensionsStatus>({});

  const [extensionsUpdatingStatus, setExtensionsUpdatingStatus] =
    useState<ExtensionsStatus>({});

  const [confirmedDeletingEntityId, setConfirmedDeletingEntityId] = useState<
    string | null
  >(null);

  const { sendRequest: sendExtensionsRequests } = useRequest<ExtensionResult>({
    request: getData
  });

  const { sendRequest: sendUpdateOrInstallExtensionRequests } =
    useRequest<InstallOrUpdateExtensionResult>({
      request: postData
    });

  const { sendRequest: sendDeleteExtensionRequests } = useRequest({
    request: deleteExtension
  });

  const getAppliedFilterCriteriasAtom = useAtomValue(
    appliedFilterCriteriasAtom
  );

  useEffect(() => {
    const types = find(propEq('types', 'name'), getAppliedFilterCriteriasAtom);
    const statuses = find(
      propEq('statuses', 'name'),
      getAppliedFilterCriteriasAtom
    );

    if (types?.value) {
      const typesValues = types.value as Array<SelectEntry>;
      setModulesActive(!!find(propEq('MODULE', 'id'), typesValues));
      setWidgetsActive(!!find(propEq('WIDGET', 'id'), typesValues));
    }

    sendExtensionsRequests({
      endpoint: buildExtensionEndPoint({
        action: 'list',
        criteriaStatus: statuses
      })
    }).then(({ status, result }) => {
      if (status) {
        setExtension(result as Extensions);

        return;
      }

      showErrorMessage(result as string);
    });
  }, [getAppliedFilterCriteriasAtom]);

  const getEntitiesByKeyAndVersionParam = (
    param,
    equals,
    key
  ): Array<EntityType> => {
    const resArray: Array<EntityType> = [];
    if (extensions) {
      for (let i = 0; i < extensions[key].entities.length; i += 1) {
        const entity = extensions[key].entities[i];
        if (entity.version[param] === equals) {
          resArray.push({
            id: entity.id,
            type: key
          });
        }
      }
    }

    return resArray;
  };

  const getAllEntitiesByVersionParam = (param, equals): Array<EntityType> => {
    if (
      (!modulesActive && !widgetsActive) ||
      (modulesActive && widgetsActive)
    ) {
      return [
        ...getEntitiesByKeyAndVersionParam(param, equals, 'module'),
        ...getEntitiesByKeyAndVersionParam(param, equals, 'widget')
      ];
    }
    if (modulesActive) {
      return [...getEntitiesByKeyAndVersionParam(param, equals, 'module')];
    }

    return [...getEntitiesByKeyAndVersionParam(param, equals, 'widget')];
  };

  const updateAllEntities = (): void => {
    const entities = getAllEntitiesByVersionParam('outdated', true);
    if (entities.length <= 0 || !entities) {
      return;
    }
    entities.forEach((entity) => {
      updateById(entity.id, entity.type);
    });
  };

  const installAllEntities = (): void => {
    const entities = getAllEntitiesByVersionParam('installed', false);
    if (entities.length <= 0 || !entities) {
      return;
    }
    entities.forEach((entity) => {
      installById(entity.id, entity.type);
    });
  };

  const updateById = (id: string, type: string): void => {
    setExtensionsUpdatingStatusByIds(id, true);
    sendUpdateOrInstallExtensionRequests({
      endpoint: buildEndPoint({
        action: 'update',
        id,
        type
      })
    })
      .then(({ status, result }) => {
        if (!status) {
          showErrorMessage(result.message as string);
        } else {
          showSuccessMessage('update succeeded');
        }

        return sendExtensionsRequests({
          endpoint: buildExtensionEndPoint({
            action: 'list',
            criteriaStatus: find(
              propEq('statuses', 'name'),
              getAppliedFilterCriteriasAtom
            )
          })
        });
      })
      .then(({ status, result }) => {
        if (status) {
          setExtension(result as Extensions);
        }
        setExtensionsUpdatingStatusByIds(id, false);
        reloadNavigation();
      });
  };

  const installById = (id: string, type: string): void => {
    setExtensionsInstallingStatusByIds(id, true);
    sendUpdateOrInstallExtensionRequests({
      endpoint: buildEndPoint({
        action: 'install',
        id,
        type
      })
    })
      .then(({ status, result }) => {
        if (!status) {
          showErrorMessage(result.message as string);
        } else {
          showSuccessMessage('Successful Installation');
        }

        return sendExtensionsRequests({
          endpoint: buildExtensionEndPoint({
            action: 'list',
            criteriaStatus: find(
              propEq('statuses', 'name'),
              getAppliedFilterCriteriasAtom
            )
          })
        });
      })
      .then(({ status, result }) => {
        if (status) {
          setExtension(result as Extensions);
        }
        setExtensionsInstallingStatusByIds(id, false);
        reloadNavigation();
      });
  };

  const setExtensionsUpdatingStatusByIds = (
    id: string,
    updating: boolean
  ): void => {
    let statuses = extensionsUpdatingStatus;
    statuses = {
      ...statuses,
      [id]: updating
    };
    setExtensionsUpdatingStatus(statuses);
  };

  const setExtensionsInstallingStatusByIds = (
    id: string,
    installing: boolean
  ): void => {
    let statuses = extensionsInstallingStatus;
    statuses = {
      ...statuses,
      [id]: installing
    };
    setExtensionsInstallingStatus(statuses);
  };

  const activateExtensionsDetails = (id: string, type: string): void => {
    setEntityDetails({
      id,
      type
    });
  };

  const onCancelToggleDeleteModal = (): void => {
    setEntityDeleting(null);
  };

  const toggleDeleteModal = (
    id: string,
    type: string,
    description: string
  ): void => {
    setEntityDeleting({
      description,
      id,
      type
    });
  };

  const hideExtensionDetails = (): void => {
    setEntityDetails(null);
  };

  const deleteById = (id: string, type: string): void => {
    setConfirmedDeletingEntityId(id);
    setEntityDeleting(null);
    sendDeleteExtensionRequests({
      id,
      type
    })
      .then(({ status, result }) => {
        setConfirmedDeletingEntityId(null);
        if (!status) {
          showErrorMessage(result as string);
        } else {
          showSuccessMessage('Successful Deletion');
        }

        return sendExtensionsRequests({
          endpoint: buildExtensionEndPoint({
            action: 'list',
            criteriaStatus: find(
              propEq('statuses', 'name'),
              getAppliedFilterCriteriasAtom
            )
          })
        });
      })
      .then(({ status, result }) => {
        if (status) {
          setExtension(result as Extensions);
        }
        reloadNavigation();
      });
  };

  const allModulesInstalled = isEmpty(
    filter(pathEq(false, ['version', 'installed']), extensions.module.entities)
  );

  const allWidgetsInstalled = isEmpty(
    filter(pathEq(false, ['version', 'installed']), extensions.widget.entities)
  );

  const allWidgetsUpToDate = isEmpty(
    filter(pathEq(true, ['version', 'outdated']), extensions.module.entities)
  );

  const allModulesUpToDate = isEmpty(
    filter(pathEq(true, ['version', 'outdated']), extensions.widget.entities)
  );

  const disableUpdate = allWidgetsUpToDate && allModulesUpToDate;

  const disableInstall = allModulesInstalled && allWidgetsInstalled;

  return (
    <Responsive margin={scrollMargin}>
      <div className={classes.contentWrapper}>
        <Stack direction="row" spacing={2}>
          <Button
            color="primary"
            data-testid="update-all"
            disabled={disableUpdate}
            size="small"
            startIcon={<UpdateIcon />}
            variant="contained"
            onClick={updateAllEntities}
          >
            {t(labelUpdateAll)}
          </Button>
          <Button
            color="primary"
            data-testid="install-all"
            disabled={disableInstall}
            size="small"
            startIcon={<InstallIcon />}
            variant="contained"
            onClick={installAllEntities}
          >
            {t(labelInstallAll)}
          </Button>
          <FederatedComponents path="/lm/administration/extensions/manager" />
        </Stack>
      </div>
      {extensions && (
        <>
          {extensions.module &&
            (modulesActive || (!modulesActive && !widgetsActive)) && (
              <ExtensionsHolder
                deletingEntityId={confirmedDeletingEntityId}
                entities={extensions.module.entities}
                installing={extensionsInstallingStatus}
                title="Modules"
                type="module"
                updating={extensionsUpdatingStatus}
                onCard={activateExtensionsDetails}
                onDelete={toggleDeleteModal}
                onInstall={installById}
                onUpdate={updateById}
              />
            )}

          {extensions.widget &&
            (widgetsActive || (!modulesActive && !widgetsActive)) && (
              <ExtensionsHolder
                deletingEntityId={confirmedDeletingEntityId}
                entities={extensions.widget.entities}
                installing={extensionsInstallingStatus}
                title="Widgets"
                type="widget"
                updating={extensionsUpdatingStatus}
                onCard={activateExtensionsDetails}
                onDelete={toggleDeleteModal}
                onInstall={installById}
                onUpdate={updateById}
              />
            )}
        </>
      )}

      {entityDetails && (
        <ExtensionDetailsPopup
          id={entityDetails.id}
          isLoading={
            extensionsInstallingStatus[entityDetails.id] ||
            extensionsUpdatingStatus[entityDetails.id] ||
            confirmedDeletingEntityId === entityDetails.id
          }
          type={entityDetails.type}
          onClose={hideExtensionDetails}
          onDelete={toggleDeleteModal}
          onInstall={installById}
          onUpdate={updateById}
        />
      )}

      {entityDeleting && (
        <ExtensionDeletePopup
          deletingEntity={entityDeleting}
          onCancel={onCancelToggleDeleteModal}
          onConfirm={deleteById}
        />
      )}
    </Responsive>
  );
};

const ExtensionsRoute = (): JSX.Element => {
  const { getNavigation } = useNavigation();
  const { getPlatformVersions } = usePlatformVersions();

  const reloadNavigation = useCallback(() => {
    getNavigation();
    getPlatformVersions();
  }, []);

  return <ExtensionsManager reloadNavigation={reloadNavigation} />;
};

export default ExtensionsRoute;
