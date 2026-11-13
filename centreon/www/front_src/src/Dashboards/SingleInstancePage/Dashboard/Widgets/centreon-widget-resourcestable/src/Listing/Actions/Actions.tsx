// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import { Grid } from '@mui/material';

import {
  DisplayType as DisplayTypeEnum,
  type ExportParameters
} from '../models';
import DisplayType from './DisplayType';
import ExportCsv from './ExportCsv';
import ResourceActions from './ResourceActions';

interface Props {
  displayType: DisplayTypeEnum;
  exportParameters?: ExportParameters;
  hasMetaService: boolean;
  setPanelOptions: (panelOptions) => void;
  isOpenTicketEnabled: boolean;
}

const Actions = ({
  displayType,
  exportParameters,
  setPanelOptions,
  hasMetaService,
  isOpenTicketEnabled
}: Props): JSX.Element => {
  return (
    <Grid container>
      <Grid flex={1} item>
        <ResourceActions />
      </Grid>
      <Grid flex={1} item>
        <DisplayType
          displayType={displayType}
          hasMetaService={hasMetaService}
          isOpenTicketEnabled={isOpenTicketEnabled}
          setPanelOptions={setPanelOptions}
        />
      </Grid>
      {exportParameters && (
        <Grid item>
          <ExportCsv parameters={exportParameters} />
        </Grid>
      )}
    </Grid>
  );
};

export default Actions;
