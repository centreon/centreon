<<<<<<< HEAD
import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { useAtomValue } from 'jotai/utils';

import { Grid } from '@mui/material';
import IconRefresh from '@mui/icons-material/Refresh';
import IconPlay from '@mui/icons-material/PlayArrow';
import IconPause from '@mui/icons-material/Pause';

import { IconButton } from '@centreon/ui';
=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { Grid } from '@material-ui/core';
import IconRefresh from '@material-ui/icons/Refresh';
import IconPlay from '@material-ui/icons/PlayArrow';
import IconPause from '@material-ui/icons/Pause';

import { IconButton, useMemoComponent } from '@centreon/ui';
>>>>>>> centreon/dev-21.10.x

import {
  labelRefresh,
  labelDisableAutorefresh,
  labelEnableAutorefresh,
} from '../../translatedLabels';
<<<<<<< HEAD
import {
  enabledAutorefreshAtom,
  sendingAtom,
} from '../../Listing/listingAtoms';
=======
import { ResourceContext, useResourceContext } from '../../Context';
>>>>>>> centreon/dev-21.10.x

interface AutorefreshProps {
  enabledAutorefresh: boolean;
  toggleAutorefresh: () => void;
}

const AutorefreshButton = ({
  enabledAutorefresh,
  toggleAutorefresh,
}: AutorefreshProps): JSX.Element => {
  const { t } = useTranslation();

  const label = enabledAutorefresh
    ? labelDisableAutorefresh
    : labelEnableAutorefresh;

  return (
    <IconButton
      ariaLabel={t(label)}
      size="small"
      title={t(label)}
      onClick={toggleAutorefresh}
    >
      {enabledAutorefresh ? <IconPause /> : <IconPlay />}
    </IconButton>
  );
};

<<<<<<< HEAD
export interface Props {
  onRefresh: () => void;
}

const RefreshActions = ({ onRefresh }: Props): JSX.Element => {
  const { t } = useTranslation();

  const [enabledAutorefresh, setEnabledAutorefresh] = useAtom(
    enabledAutorefreshAtom,
  );
  const sending = useAtomValue(sendingAtom);
=======
export interface ActionsProps {
  onRefresh: () => void;
}

type ResourceContextProps = Pick<
  ResourceContext,
  | 'enabledAutorefresh'
  | 'setEnabledAutorefresh'
  | 'sending'
  | 'selectedResourceId'
>;

const RefreshActionsContent = ({
  onRefresh,
  enabledAutorefresh,
  setEnabledAutorefresh,
  sending,
}: ActionsProps & ResourceContextProps): JSX.Element => {
  const { t } = useTranslation();
>>>>>>> centreon/dev-21.10.x

  const toggleAutorefresh = (): void => {
    setEnabledAutorefresh(!enabledAutorefresh);
  };

  return (
    <Grid container spacing={1}>
      <Grid item>
        <IconButton
          ariaLabel={t(labelRefresh)}
          data-testid={labelRefresh}
          disabled={sending}
          size="small"
          title={t(labelRefresh)}
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
  );
};

<<<<<<< HEAD
=======
const RefreshActions = ({ onRefresh }: ActionsProps): JSX.Element => {
  const {
    enabledAutorefresh,
    setEnabledAutorefresh,
    sending,
    selectedResourceId,
  } = useResourceContext();

  return useMemoComponent({
    Component: (
      <RefreshActionsContent
        enabledAutorefresh={enabledAutorefresh}
        sending={sending}
        setEnabledAutorefresh={setEnabledAutorefresh}
        onRefresh={onRefresh}
      />
    ),
    memoProps: [sending, enabledAutorefresh, selectedResourceId],
  });
};

>>>>>>> centreon/dev-21.10.x
export default RefreshActions;
