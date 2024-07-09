import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import IconPause from '@mui/icons-material/Pause';
import IconPlay from '@mui/icons-material/PlayArrow';
import IconRefresh from '@mui/icons-material/Refresh';
import { Grid } from '@mui/material';

import { IconButton } from '@centreon/ui';

import {
  enabledAutorefreshAtom,
  sendingAtom
} from '../../Listing/listingAtoms';
import {
  labelDisableAutorefresh,
  labelEnableAutorefresh,
  labelRefresh
} from '../../translatedLabels';
import ActionMenuItem from '../Resource/ActionMenuItem';

import { useStyles } from './refresh.styles';

interface AutorefreshProps {
  enabledAutorefresh: boolean;
  toggleAutorefresh: () => void;
}

const AutorefreshButton = ({
  enabledAutorefresh,
  toggleAutorefresh
}: AutorefreshProps): JSX.Element => {
  const { t } = useTranslation();

  const label = enabledAutorefresh
    ? labelDisableAutorefresh
    : labelEnableAutorefresh;

  return (
    <IconButton
      ariaLabel={t(label) as string}
      data-testid="Disable autorefresh"
      size="small"
      title={t(label) as string}
      onClick={toggleAutorefresh}
    >
      {enabledAutorefresh ? <IconPause /> : <IconPlay />}
    </IconButton>
  );
};

interface DisplayAsList {
  close: () => void;
  display: boolean;
}
export interface Props {
  displayAsIcons?: boolean;
  displayAsList?: DisplayAsList;
  onRefresh: () => void;
}

const RefreshActions = ({
  onRefresh,
  displayAsIcons = true,
  displayAsList
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [enabledAutorefresh, setEnabledAutorefresh] = useAtom(
    enabledAutorefreshAtom
  );

  const sending = useAtomValue(sendingAtom);

  const toggleAutorefresh = (): void => {
    setEnabledAutorefresh(!enabledAutorefresh);
  };

  return (
    <>
      {displayAsIcons && (
        <Grid container className={classes.container}>
          <Grid item>
            <IconButton
              ariaLabel={t(labelRefresh) as string}
              data-testid="Refresh"
              disabled={sending}
              size="small"
              title={t(labelRefresh) as string}
              onClick={onRefresh}
            >
              <IconRefresh />
            </IconButton>
          </Grid>
          <Grid item>
            <AutorefreshButton
              enabledAutorefresh={enabledAutorefresh}
              toggleAutorefresh={toggleAutorefresh}
            />
          </Grid>
        </Grid>
      )}
      {displayAsList?.display && (
        <>
          <ActionMenuItem
            permitted
            disabled={false}
            label={labelRefresh}
            testId="RefreshInMoreActions"
            onClick={() => {
              onRefresh();
              displayAsList.close();
            }}
          />
          <ActionMenuItem
            permitted
            disabled={false}
            label={
              enabledAutorefresh
                ? labelDisableAutorefresh
                : labelEnableAutorefresh
            }
            testId="AutorefreshInMoreActions"
            onClick={() => {
              toggleAutorefresh();
              displayAsList.close();
            }}
          />
        </>
      )}
    </>
  );
};

export default RefreshActions;
