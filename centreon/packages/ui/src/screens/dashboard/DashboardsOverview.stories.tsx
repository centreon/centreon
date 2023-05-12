import { useEffect } from 'react';

import { Meta } from '@storybook/react';
import { atom, useAtom } from 'jotai';

import { Add as AddIcon } from '@mui/icons-material';

import { Header } from '../../components/Header';
import { Button } from '../../components/Button';
import { List, ListItem, ListEmptyState } from '../../components/List';
import {
  DashboardForm,
  DashboardFormProps
} from '../../components/Form/Dashboard';
import { Default as DashboardFormDefaultStory } from '../../components/Form/Dashboard/DashboardForm.stories';
import { SimpleDialog } from '../../components/Dialog';
import {
  TiledListingPage,
  TiledListingActions,
  TiledListingList,
  TiledListingContent
} from '../../layout/TiledListingPage';

const meta: Meta = {
  args: {
    actions: {
      create: {
        label: 'Create a dashboard'
      }
    },
    list: {
      emptyState: {
        labels: {
          actions: {
            create: 'Create dashboard'
          },
          title: 'No dashboards found'
        }
      }
    },
    title: 'Dashboards overview'
  },
  title: 'screens/Dashboard/Dashboards overview'
};

export default meta;

interface dashboardItem {
  description: string;
  id: number;
  name: string;
}

const dialogStateAtom = atom<{
  item: dashboardItem | null;
  open: boolean;
  variant: DashboardFormProps['variant'];
}>({
  item: null,
  open: false,
  variant: 'create'
});

const dataDashboardsAtom = atom<Array<dashboardItem>>([]);

const DefaultView = (args): JSX.Element => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const [dataDashboards, setDataDashboards] = useAtom(dataDashboardsAtom);

  useEffect(() => {
    setDataDashboards(args.data.dashboards);
  }, [args.data.dashboards]);

  const createDashboard = (data): void => {
    data.id = dataDashboards.length
      ? Math.max(...dataDashboards.map((dashboard) => dashboard.id)) + 1
      : 0;
    setDataDashboards((prev) =>
      [...prev, data].sort((a, b) => a.name.localeCompare(b.name))
    );
    setDialogState({ item: null, open: false, variant: 'create' });
  };

  const updateDashboard = (data): void => {
    setDataDashboards((prev) =>
      prev
        .map((dashboard) => (dashboard.id === data.id ? data : dashboard))
        .sort((a, b) => a.name.localeCompare(b.name))
    );
    setDialogState({ item: null, open: false, variant: 'update' });
  };

  const deleteDashboard = (id): void => {
    setDataDashboards((prev) =>
      prev.filter((dashboard) => dashboard.id !== id)
    );
  };

  return (
    <TiledListingPage>
      <Header title={args.title} />
      <TiledListingList>
        <TiledListingActions>
          {dataDashboards.length !== 0 && (
            <Button
              dataTestId="create-dashboard"
              icon={<AddIcon />}
              iconVariant="start"
              onClick={(): void =>
                setDialogState({ item: null, open: true, variant: 'create' })
              }
            >
              {args.actions.create.label}
            </Button>
          )}
        </TiledListingActions>
        <TiledListingContent>
          {dataDashboards.length === 0 ? (
            <ListEmptyState
              dataTestId="create-dashboard"
              labels={args.list.emptyState.labels}
              onCreate={() =>
                setDialogState({ item: null, open: true, variant: 'create' })
              }
            />
          ) : (
            <List>
              {dataDashboards.map((dashboard) => (
                <ListItem
                  hasActions
                  hasCardAction
                  description={dashboard.description}
                  key={dashboard.id}
                  title={dashboard.name}
                  onDelete={(): void => deleteDashboard(dashboard.id)}
                  onEdit={(): void =>
                    setDialogState({
                      item: dashboard,
                      open: true,
                      variant: 'update'
                    })
                  }
                />
              ))}
            </List>
          )}
        </TiledListingContent>
        <SimpleDialog
          open={dialogState.open}
          onClose={(): void =>
            setDialogState({ item: null, open: false, variant: 'create' })
          }
        >
          <DashboardForm
            labels={DashboardFormDefaultStory!.args!.labels!}
            resource={dialogState.item || undefined}
            variant={dialogState.variant}
            onCancel={(): void =>
              setDialogState({
                item: null,
                open: false,
                variant: dialogState.variant
              })
            }
            onSubmit={(values): void => {
              dialogState.variant === 'create'
                ? createDashboard(values)
                : updateDashboard(values);
            }}
          />
        </SimpleDialog>
      </TiledListingList>
    </TiledListingPage>
  );
};

export const Default = {
  args: {
    data: {
      dashboards: [
        { description: 'Dashboard 1 description', id: 1, name: 'Dashboard 1' },
        { description: 'Dashboard 2 description', id: 2, name: 'Dashboard 2' },
        { description: 'Dashboard 3 description', id: 3, name: 'Dashboard 3' },
        { description: 'Dashboard 4 description', id: 4, name: 'Dashboard 4' },
        { description: 'Dashboard 5 description', id: 5, name: 'Dashboard 5' }
      ]
    }
  },
  render: DefaultView
};

export const AsInitialState = {
  args: {
    data: {
      dashboards: []
    }
  },
  render: DefaultView
};
