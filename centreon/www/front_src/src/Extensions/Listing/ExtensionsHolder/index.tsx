import InstallIcon from '@mui/icons-material/Add';
import CheckIcon from '@mui/icons-material/Check';
import DeleteIcon from '@mui/icons-material/Delete';
import UpdateIcon from '@mui/icons-material/SystemUpdateAlt';
import {
  Button,
  Card,
  CardActions,
  CardContent,
  Chip,
  Divider,
  Grid,
  LinearProgress,
  Paper,
  Typography
} from '@mui/material';
import Stack from '@mui/material/Stack';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import dayjs from 'dayjs';
import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  labelLicenseEndDate,
  labelLicenseExpired,
  labelLicenseNotValid,
  labelLicenseRequired
} from '../../translatedLabels';
import { Entity, ExtensionsStatus, LicenseProps } from '../models';

const useStyles = makeStyles()((theme) => ({
  contentWrapper: {
    [theme.breakpoints.up(767)]: {
      padding: theme.spacing(1.5, 1.5, 1.5, 0)
    },
    boxSizing: 'border-box',
    margin: theme.spacing(0, 'auto'),
    padding: theme.spacing(1.5, 2.5, 0, 0)
  },
  extensionsTypes: {
    color: theme.palette.text.primary
  },
  license: {
    alignItems: 'center',
    cursor: 'pointer',
    display: 'flex',
    justifyContent: 'center',
    minHeight: '20px'
  },
  licenseInvalid: {
    backgroundColor: theme.palette.error.main
  },
  licenseValid: {
    backgroundColor: theme.palette.success.main
  }
}));

interface ChipAvatarProps {
  entity: Entity;
  onUpdate: (id: string, type: string) => void;
  type: string;
}

const ChipAvatar = ({
  entity: {
    id,
    is_internal,
    version: { outdated }
  },
  onUpdate,
  type
}: ChipAvatarProps): JSX.Element | null => {
  if (is_internal) {
    return null;
  }

  if (outdated) {
    return (
      <UpdateIcon
        onClick={(e): void => {
          e.preventDefault();
          e.stopPropagation();

          onUpdate(id, type);
        }}
        style={{
          color: '#FFFFFF',
          cursor: 'pointer'
        }}
      />
    );
  }

  return <CheckIcon style={{ color: '#FFFFFF' }} />;
};

interface Props {
  deletingEntityId: string | null;
  entities: Array<Entity>;
  installing: ExtensionsStatus;
  onCard: (id: string, type: string) => void;
  onDelete: (id: string, type: string, description: string) => void;
  onInstall: (id: string, type: string) => void;
  onUpdate: (id: string, type: string) => void;
  title: string;
  type: string;
  updating: ExtensionsStatus;
}

const ExtensionsHolder = ({
  title,
  entities,
  onInstall,
  onUpdate,
  onDelete,
  onCard,
  updating,
  installing,
  deletingEntityId,
  type
}: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const { timezone } = useAtomValue(userAtom);
  const { toDate } = useLocaleDateTimeFormat();

  const parseDescription = (description): string => {
    return description.replace(/^centreon\s+(\w+)/i, (_, v) => v);
  };

  const getPropsFromLicense = (licenseInfo): LicenseProps | undefined => {
    if (!licenseInfo || !licenseInfo.required) {
      return undefined;
    }

    if (isNil(licenseInfo.expiration_date)) {
      return {
        isInvalid: true,
        label: t(labelLicenseRequired)
      };
    }

    if (Number.isNaN(Date.parse(licenseInfo.expiration_date))) {
      return {
        isInvalid: true,
        label: t(labelLicenseNotValid)
      };
    }

    const isLicenseExpired = dayjs()
      .tz(timezone)
      .isAfter(dayjs(licenseInfo.expiration_date).tz(timezone));

    if (isLicenseExpired) {
      return {
        isInvalid: true,
        label: t(labelLicenseExpired)
      };
    }

    return {
      isInvalid: false,
      label: `${t(labelLicenseEndDate)} ${toDate(licenseInfo.expiration_date)}`
    };
  };

  return (
    <div className={classes.contentWrapper}>
      <Stack>
        <Grid
          alignItems="center"
          container
          direction="row"
          spacing={1}
          style={{ marginBottom: 8, width: '100%' }}
        >
          <Grid item>
            <Typography className={classes.extensionsTypes} variant="body1">
              {title}
            </Typography>
          </Grid>
          <Grid item style={{ flexGrow: 1 }}>
            <Divider style={{ backgroundColor: 'rgba(0, 0, 0, 0.12)' }} />
          </Grid>
        </Grid>
        <Grid
          alignItems="stretch"
          container
          spacing={2}
          style={{ cursor: 'pointer' }}
        >
          {entities.map((entity) => {
            const isLoading =
              installing[entity.id] ||
              updating[entity.id] ||
              deletingEntityId === entity.id;

            const licenseInfo = getPropsFromLicense(entity.license);

            return (
              <Grid
                id={`${type}-${entity.id}`}
                item
                key={entity.id}
                onClick={(): void => {
                  onCard(entity.id, type);
                }}
                style={{ width: 220 }}
              >
                <Card
                  style={{
                    display: 'grid',
                    gridTemplateRows: '1fr 0.5fr min-content',
                    height: '100%'
                  }}
                  variant="outlined"
                >
                  {isLoading && <LinearProgress />}
                  <CardContent style={{ padding: '10px' }}>
                    <Typography style={{ fontWeight: 'bold' }} variant="body1">
                      {parseDescription(entity.description)}
                    </Typography>
                    <Typography variant="body2">
                      {`by ${entity.label}`}
                    </Typography>
                  </CardContent>
                  <CardActions style={{ justifyContent: 'center' }}>
                    {entity.version.installed ? (
                      <Chip
                        avatar={
                          <ChipAvatar
                            entity={entity}
                            onUpdate={onUpdate}
                            type={type}
                          />
                        }
                        deleteIcon={<DeleteIcon style={{ color: '#FFFFFF' }} />}
                        disabled={isLoading}
                        label={
                          !entity.is_internal ? (
                            entity.version.current
                          ) : (
                            <CheckIcon style={{ color: '#FFFFFF' }} />
                          )
                        }
                        onDelete={
                          !entity.is_internal
                            ? (): void =>
                                onDelete(entity.id, type, entity.description)
                            : undefined
                        }
                        style={{
                          backgroundColor: entity.version.outdated
                            ? '#FF9A13'
                            : '#84BD00',
                          color: '#FFFFFF'
                        }}
                      />
                    ) : (
                      <Button
                        color="primary"
                        disabled={isLoading}
                        onClick={(e): void => {
                          e.preventDefault();
                          e.stopPropagation();
                          const { id } = entity;
                          const { version } = entity;
                          if (version.outdated && !updating[entity.id]) {
                            onUpdate(id, type);
                          } else if (
                            !version.installed &&
                            !installing[entity.id]
                          ) {
                            onInstall(id, type);
                          }
                        }}
                        size="small"
                        startIcon={!entity.version.installed && <InstallIcon />}
                        variant="contained"
                      >
                        {entity.version.available}
                      </Button>
                    )}
                  </CardActions>
                  <Paper
                    className={cx(classes.license, {
                      [classes.licenseValid]: equals(
                        licenseInfo?.isInvalid,
                        false
                      ),
                      [classes.licenseInvalid]: equals(
                        licenseInfo?.isInvalid,
                        true
                      )
                    })}
                    elevation={0}
                    square
                  >
                    {licenseInfo?.label && (
                      <Typography style={{ color: '#FFFFFF' }} variant="body2">
                        {licenseInfo.label}
                      </Typography>
                    )}
                  </Paper>
                </Card>
              </Grid>
            );
          })}
        </Grid>
      </Stack>
    </div>
  );
};

export default ExtensionsHolder;
