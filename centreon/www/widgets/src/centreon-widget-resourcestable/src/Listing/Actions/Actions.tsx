import { Grid } from '@mui/material';

import { DisplayType as DisplayTypeEnum } from '../models';

import DisplayType from './DisplayType';
import ResourceActions from './ResourceActions';

import { isOnPublicPageAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';

interface Props {
  displayType: DisplayTypeEnum;
  hasMetaService: boolean;
  setPanelOptions: (panelOptions) => void;
  isOpenTicketEnabled: boolean;
}

const Actions = ({
  displayType,
  setPanelOptions,
  hasMetaService,
  isOpenTicketEnabled
}: Props): JSX.Element => {
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  return (
    <Grid container>
      <Grid item flex={1}>
        {!isOnPublicPage && <ResourceActions />}
      </Grid>
      <Grid item flex={1}>
        <DisplayType
          displayType={displayType}
          hasMetaService={hasMetaService}
          setPanelOptions={setPanelOptions}
          isOpenTicketEnabled={isOpenTicketEnabled}
        />
      </Grid>
    </Grid>
  );
};

export default Actions;
