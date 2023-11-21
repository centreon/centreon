import { useState, useEffect } from 'react';

import Carousel from 'react-material-ui-carousel';
import { useTranslation } from 'react-i18next';
import { equals, isEmpty, length, not } from 'ramda';

import {
  Chip,
  Typography,
  Divider,
  Grid,
  Button,
  Link,
  CircularProgress
} from '@mui/material';
import UpdateIcon from '@mui/icons-material/SystemUpdateAlt';
import DeleteIcon from '@mui/icons-material/Delete';
import InstallIcon from '@mui/icons-material/Add';

import { Dialog, useRequest, getData, ParentSize } from '@centreon/ui';

import {
  labelAvailable,
  labelDescription,
  labelLastUpdate,
  labelInstall,
  labelDelete,
  labelUpdate
} from '../../translatedLabels';
import { Entity, ExtensionDetails } from '../models';
import { buildEndPoint } from '../api/endpoint';

import {
  SliderSkeleton,
  HeaderSkeleton,
  ContentSkeleton,
  ReleaseNoteSkeleton
} from './LoadingSkeleton';

interface Props {
  id: string;
  isLoading: boolean;
  onClose: () => void;
  onDelete: (id: string, type: string, description: string) => void;
  onInstall: (id: string, type: string) => void;
  onUpdate: (id: string, type: string) => void;
  type: string;
}

const hasImages = (images: Array<string>): boolean => not(isEmpty(images));

const hasOneImage = (images: Array<string>): boolean =>
  equals(1, length(images));

const imageHeight = 280;

const ExtensionDetailPopup = ({
  id,
  type,
  onClose,
  onDelete,
  onInstall,
  onUpdate,
  isLoading
}: Props): JSX.Element | null => {
  const { t } = useTranslation();
  const [extensionDetails, setExtensionDetails] = useState<Entity | null>(null);
  const [loading, setLoading] = useState<boolean>(true);

  const { sendRequest: sendExtensionDetailsValueRequests } =
    useRequest<ExtensionDetails>({
      request: getData
    });

  useEffect(() => {
    sendExtensionDetailsValueRequests({
      endpoint: buildEndPoint({
        action: 'details',
        id,
        type
      })
    }).then((data) => {
      const { result } = data;
      if (result.images) {
        result.images = result.images.map((image) => {
          return `./${image}`;
        });
      }
      setExtensionDetails(result);
      setLoading(false);
    });
  }, [isLoading]);

  if (extensionDetails === null) {
    return null;
  }

  const updateExtension = (): void => {
    onUpdate(extensionDetails.id, extensionDetails.type);
  };

  const installExtension = (): void => {
    onInstall(extensionDetails.id, extensionDetails.type);
  };

  const deleteExtension = (): void => {
    onDelete(
      extensionDetails.id,
      extensionDetails.type,
      extensionDetails.title
    );
  };

  return (
    <Dialog
      open
      labelConfirm="Close"
      labelTitle=""
      onClose={onClose}
      onConfirm={onClose}
    >
      <Grid container direction="column" spacing={2} sx={{ width: 540 }}>
        {hasImages(extensionDetails.images) && (
          <Grid item>
            <ParentSize>
              {({ width }): JSX.Element =>
                extensionDetails.images ? (
                  <Carousel
                    cycleNavigation
                    fullHeightHover
                    animation="slide"
                    autoPlay={false}
                    height={imageHeight}
                    indicators={!hasOneImage(extensionDetails.images)}
                    navButtonsAlwaysInvisible={hasOneImage(
                      extensionDetails.images
                    )}
                  >
                    {extensionDetails.images?.map((image) => (
                      <img
                        alt={image}
                        height="100%"
                        key={image}
                        src={image}
                        width="100%"
                      />
                    ))}
                  </Carousel>
                ) : (
                  <SliderSkeleton width={width} />
                )
              }
            </ParentSize>
          </Grid>
        )}
        <Grid item>
          {loading ? (
            <HeaderSkeleton />
          ) : (
            <Grid container spacing={2}>
              <Grid item>
                <Typography variant="h5">{extensionDetails.title}</Typography>
              </Grid>
              {extensionDetails.version.installed &&
                extensionDetails.version.outdated && (
                  <Grid item>
                    <Button
                      color="primary"
                      disabled={isLoading}
                      endIcon={isLoading && <CircularProgress size={15} />}
                      size="small"
                      startIcon={<UpdateIcon />}
                      variant="contained"
                      onClick={updateExtension}
                    >
                      {t(labelUpdate)}
                    </Button>
                  </Grid>
                )}
              {!extensionDetails.is_internal &&
                extensionDetails.version.installed && (
                  <Grid item>
                    <Button
                      color="primary"
                      disabled={isLoading}
                      endIcon={isLoading && <CircularProgress size={15} />}
                      size="small"
                      startIcon={<DeleteIcon />}
                      variant="contained"
                      onClick={deleteExtension}
                    >
                      {t(labelDelete)}
                    </Button>
                  </Grid>
                )}
              {!extensionDetails.is_internal &&
                !extensionDetails.version.installed && (
                  <Grid item>
                    <Button
                      color="primary"
                      disabled={isLoading}
                      endIcon={isLoading && <CircularProgress size={15} />}
                      size="small"
                      startIcon={<InstallIcon />}
                      variant="contained"
                      onClick={installExtension}
                    >
                      {t(labelInstall)}
                    </Button>
                  </Grid>
                )}
            </Grid>
          )}
        </Grid>
        {!extensionDetails.is_internal ? (
          <Grid item>
            <Grid container spacing={1}>
              <Grid item>
                <Chip
                  label={
                    (!extensionDetails.version.installed
                      ? `${t(labelAvailable)} `
                      : '') + extensionDetails.version.available
                  }
                />
              </Grid>
              <Grid item>
                <Chip label={extensionDetails.stability} />
              </Grid>
            </Grid>
          </Grid>
        ) : null}
        <Grid item>
          {loading ? (
            <ContentSkeleton />
          ) : (
            <>
              {extensionDetails.last_update && (
                <Typography variant="body1">
                  {`${t(labelLastUpdate)} ${extensionDetails.last_update}`}
                </Typography>
              )}
              <Typography variant="h6">{t(labelDescription)}</Typography>
              <Typography variant="body2">
                {extensionDetails.description}
              </Typography>
            </>
          )}
        </Grid>
        {extensionDetails.release_note && (
          <>
            <Grid item>
              <Divider />
            </Grid>
            <Grid item>
              {loading ? (
                <ReleaseNoteSkeleton />
              ) : (
                <Link href={extensionDetails.release_note}>
                  <Typography>{extensionDetails.release_note}</Typography>
                </Link>
              )}
            </Grid>
          </>
        )}
      </Grid>
    </Dialog>
  );
};

export default ExtensionDetailPopup;
