import { Button, List, ListItem, Typography } from '@mui/material';

import { isEmpty } from 'ramda';
import { ReactElement } from 'react';

import CloudInstallCommand from './CloudInstallCommand/CloudInstallCommand';
import ExportConfiguration from './ExportConfiguration';

export interface PollerSubMenuProps {
  allPollerLabel: string;
  closeSubMenu: () => void;
  displayPollerButton: boolean;
  exportConfig: {
    isExportButtonEnabled: boolean;
  };
  issues: Array<{
    key: string;
    text: string;
    total: string;
  }>;
  pollerConfig: {
    label: string;
    redirect: () => void;
    testId: string;
  };
  pollerCount: number;
}

export const PollerSubMenu = ({
  closeSubMenu,
  issues,
  pollerCount,
  allPollerLabel,
  pollerConfig,
  exportConfig,
  displayPollerButton
}: PollerSubMenuProps): ReactElement => {
  return (
    <List className="min-w-[216px] p-0" data-testid="poller-menu">
      {!isEmpty(issues) ? (
        issues.map(({ text, total, key }) => {
          return (
            <ListItem
              className="p-2 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-divider flex justify-between"
              data-testid="pollerIssues"
              key={key}
            >
              <Typography className="grow" variant="body2">
                {text}
              </Typography>
              <Typography variant="body2">{total}</Typography>
            </ListItem>
          );
        })
      ) : (
        <ListItem className="p-2 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-divider flex justify-between">
          <Typography variant="body2">{allPollerLabel}</Typography>
          <Typography variant="body2">{pollerCount as number}</Typography>
        </ListItem>
      )}
      {displayPollerButton && (
        <ListItem
          className="p-2 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-divider"
          onClick={closeSubMenu}
        >
          <Button
            data-testid={pollerConfig.testId}
            fullWidth
            onClick={pollerConfig.redirect}
            size="small"
            variant="outlined"
          >
            {pollerConfig.label}
          </Button>
        </ListItem>
      )}
      {exportConfig.isExportButtonEnabled && (
        <ListItem className="p-2 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-divider">
          <ExportConfiguration closeSubMenu={closeSubMenu} />
        </ListItem>
      )}

      <ListItem className="p-2 [&:not(:last-child)]:border-b [&:not(:last-child)]:border-divider">
        <CloudInstallCommand closeSubMenu={closeSubMenu} />
      </ListItem>
    </List>
  );
};
