import IconPause from '@mui/icons-material/Pause';
import IconPlay from '@mui/icons-material/PlayArrow';
import IconRefresh from '@mui/icons-material/Refresh';
import { Grid } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

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
      onClick={toggleAutorefresh}
      size="small"
      title={t(label) as string}
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
        <Grid className={classes.container} container>
          <Grid item>
            <IconButton
              ariaLabel={t(labelRefresh) as string}
              data-testid="Refresh"
              disabled={sending}
              onClick={onRefresh}
              size="small"
              title={t(labelRefresh) as string}
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
            disabled={false}
            label={labelRefresh}
            onClick={() => {
              onRefresh();
              displayAsList.close();
            }}
            permitted
            testId="RefreshInMoreActions"
          />
          <ActionMenuItem
            disabled={false}
            label={
              enabledAutorefresh
                ? labelDisableAutorefresh
                : labelEnableAutorefresh
            }
            onClick={() => {
              toggleAutorefresh();
              displayAsList.close();
            }}
            permitted
            testId="AutorefreshInMoreActions"
          />
        </>
      )}
    </>
  );
};

export default RefreshActions;
