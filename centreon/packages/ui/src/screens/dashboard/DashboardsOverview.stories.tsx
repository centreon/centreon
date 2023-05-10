import { useEffect } from 'react';

import { Meta } from '@storybook/react';
import { atom, useAtom } from 'jotai';

import { Add as AddIcon } from '@mui/icons-material';

import { Header } from '../../components/Header';
import { Button } from '../../components/Button';
import { List } from '../../components/List';
import {
  DashboardForm,
  DashboardFormProps
} from '../../components/Form/Dashboard';
import { Default as DashboardFormDefaultStory } from '../../components/Form/Dashboard/DashboardForm.stories';
import { Dialog } from '../../components/Dialog';

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

const DefaultView = (args) => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const [dataDashboards, setDataDashboards] = useAtom(dataDashboardsAtom);

  useEffect(() => {
    setDataDashboards(args.data.dashboards);
  }, [args.data.dashboards]);

  const createDashboard = (data) => {
    data.id = dataDashboards.length
      ? Math.max(...dataDashboards.map((dashboard) => dashboard.id)) + 1
      : 0;
    setDataDashboards((prev) =>
      [...prev, data].sort((a, b) => a.name.localeCompare(b.name))
    );
    setDialogState({ item: null, open: false, variant: 'create' });
  };

  const updateDashboard = (data) => {
    setDataDashboards((prev) =>
      prev
        .map((dashboard) => (dashboard.id === data.id ? data : dashboard))
        .sort((a, b) => a.name.localeCompare(b.name))
    );
    setDialogState({ item: null, open: false, variant: 'update' });
  };

  const deleteDashboard = (id) => {
    setDataDashboards((prev) =>
      prev.filter((dashboard) => dashboard.id !== id)
    );
  };

  return (
    <div>
      <Header title={args.title} />
      <div className="dashboards-list">
        {' '}
        {/* TODO DashboardsListLayout */}
        <div className="dashboard-list-header">
          {' '}
          {/* TODO DashboardsList */}
          <div className="actions" style={{ paddingBottom: '20px' }}>
            {' '}
            {/* TODO DashboardsList.Actions */}
            {dataDashboards.length !== 0 && (
              <Button
                icon={<AddIcon />}
                iconVariant="start"
                variant="primary"
                onClick={() =>
                  setDialogState({ item: null, open: true, variant: 'create' })
                }
              >
                {args.actions.create.label}
              </Button>
            )}
          </div>
          <div className="content" style={{ paddingBottom: '20px' }}>
            {' '}
            {/* TODO DashboardsList.Content */}
            {dataDashboards.length === 0 ? (
              <List.EmptyState
                labels={args.list.emptyState.labels}
                onCreate={() =>
                  setDialogState({ item: null, open: true, variant: 'create' })
                }
              />
            ) : (
              <List>
                {dataDashboards.map((dashboard) => (
                  <List.Item
                    hasActions
                    hasCardAction
                    description={dashboard.description}
                    key={dashboard.id}
                    title={dashboard.name}
                    onDelete={() => deleteDashboard(dashboard.id)}
                    onEdit={() =>
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
          </div>
          <Dialog
            open={dialogState.open}
            onClose={() =>
              setDialogState({ item: null, open: false, variant: 'create' })
            }
          >
            <DashboardForm
              labels={DashboardFormDefaultStory!.args!.labels!}
              resource={dialogState.item}
              variant={dialogState.variant}
              onCancel={() =>
                setDialogState({
                  item: null,
                  open: false,
                  variant: dialogState.variant
                })
              }
              onSubmit={(values) => {
                dialogState.variant === 'create'
                  ? createDashboard(values)
                  : updateDashboard(values);
              }}
            />
          </Dialog>
        </div>
      </div>
    </div>
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
