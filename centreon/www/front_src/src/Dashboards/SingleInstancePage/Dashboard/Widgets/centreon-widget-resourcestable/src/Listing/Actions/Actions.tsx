// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Grid } from '@mui/material';

import { DisplayType as DisplayTypeEnum } from '../models';
import DisplayType from './DisplayType';
import ResourceActions from './ResourceActions';

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
  return (
    <Grid container>
      <Grid
        item
        sx={{
          flex: 1
        }}
      >
        <ResourceActions />
      </Grid>
      <Grid
        item
        sx={{
          flex: 1
        }}
      >
        <DisplayType
          displayType={displayType}
          hasMetaService={hasMetaService}
          isOpenTicketEnabled={isOpenTicketEnabled}
          setPanelOptions={setPanelOptions}
        />
      </Grid>
    </Grid>
  );
};

export default Actions;
