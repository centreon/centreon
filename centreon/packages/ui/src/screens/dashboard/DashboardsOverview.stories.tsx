/* eslint-disable  @typescript-eslint/no-non-null-assertion */
import { useEffect } from 'react';

import { Meta } from '@storybook/react';
import { atom, useAtom } from 'jotai';

import { Add as AddIcon } from '@mui/icons-material';

import {
  Button,
  DashboardForm,
  DashboardFormProps,
  DataTable,
  Header,
  Modal
} from '../../components';
import { Default as DashboardFormDefaultStory } from '../../components/Form/Dashboard/DashboardForm.stories';
import { PageLayout } from '../../components/Layout/PageLayout';

const meta: Meta = {
  args: {
    actions: {
      create: {
        label: 'Create a dashboard'
      }
    },
    deleteConfirmation: {
      labels: {
        actions: {
          cancel: 'Cancel',
          confirm: 'Delete'
        },
        description: (name) => (
          <>
            Are you sure you want to delete <strong>{name}</strong> ?
          </>
        ),
        title: 'Delete dashboard'
      }
    },
    form: {
      labels: {
        ...DashboardFormDefaultStory.args!.labels,
        title: {
          create: 'Create dashboard',
          update: 'Update dashboard'
        }
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
  parameters: {
    layout: 'fullscreen'
  },
  title: 'screens/Dashboards'
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

const deleteDialogStateAtom = atom<{
  item: dashboardItem | null;
  open: boolean;
}>({
  item: null,
  open: false
});

const dataDashboardsAtom = atom<Array<dashboardItem>>([]);

const DefaultView = (args): JSX.Element => {
  const { data, title, actions, list, form, deleteConfirmation } = args;
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const [deleteDialogState, setDeleteDialogState] = useAtom(
    deleteDialogStateAtom
  );
  const [dataDashboards, setDataDashboards] = useAtom(dataDashboardsAtom);

  useEffect(() => {
    setDataDashboards(data.dashboards);
  }, [data.dashboards]);

  const createDashboard = (d): void => {
    const dashboard = { ...d };
    dashboard.id = dataDashboards.length
      ? Math.max(...dataDashboards.map((db) => db.id)) + 1
      : 0;
    setDataDashboards((prev) =>
      [...prev, dashboard].sort((a, b) => a.name.localeCompare(b.name))
    );
    setDialogState({ item: null, open: false, variant: 'create' });
  };

  const updateDashboard = (d): void => {
    setDataDashboards((prev) =>
      prev
        .map((dashboard) => (dashboard.id === d.id ? d : dashboard))
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
    <PageLayout>
      <PageLayout.Header>
        <Header title={title} />
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions>
          {dataDashboards.length !== 0 && (
            <Button
              aria-label="add"
              icon={<AddIcon />}
              iconVariant="start"
              onClick={() =>
                setDialogState({ item: null, open: true, variant: 'create' })
              }
            >
              {actions.create.label}
            </Button>
          )}
        </PageLayout.Actions>

        <DataTable isEmpty={dataDashboards.length === 0}>
          {dataDashboards.length === 0 ? (
            <DataTable.EmptyState
              labels={list.emptyState.labels}
              onCreate={() =>
                setDialogState({
                  item: null,
                  open: true,
                  variant: 'create'
                })
              }
            />
          ) : (
            dataDashboards.map((dashboard) => (
              <DataTable.Item
                hasActions
                hasCardAction
                description={dashboard.description}
                key={dashboard.id}
                title={dashboard.name}
                onDelete={() =>
                  setDeleteDialogState({ item: dashboard, open: true })
                }
                onEdit={() =>
                  setDialogState({
                    item: dashboard,
                    open: true,
                    variant: 'update'
                  })
                }
              />
            ))
          )}
        </DataTable>
      </PageLayout.Body>

      <Modal
        open={dialogState.open}
        onClose={() =>
          setDialogState({
            item: null,
            open: false,
            variant: dialogState.variant
          })
        }
      >
        <Modal.Header>
          {form.labels.title[dialogState.variant ?? 'create']}
        </Modal.Header>
        <Modal.Body>
          <DashboardForm
            labels={DashboardFormDefaultStory!.args!.labels!}
            resource={dialogState.item || undefined}
            variant={dialogState.variant}
            onCancel={() =>
              setDialogState({
                item: null,
                open: false,
                variant: dialogState.variant
              })
            }
            onSubmit={(values) =>
              dialogState.variant === 'create'
                ? createDashboard(values)
                : updateDashboard(values)
            }
          />
        </Modal.Body>
      </Modal>
      <Modal
        open={deleteDialogState.open}
        onClose={() =>
          setDeleteDialogState({
            ...deleteDialogState,
            open: false
          })
        }
      >
        <Modal.Header>{deleteConfirmation.labels.title}</Modal.Header>
        <Modal.Body>
          <p>
            {deleteConfirmation.labels.description(
              deleteDialogState.item?.name
            )}
          </p>
        </Modal.Body>
        <Modal.Actions
          isDanger
          labels={deleteConfirmation.labels.actions}
          onCancel={() => setDeleteDialogState({ item: null, open: false })}
          onConfirm={() => {
            deleteDashboard(deleteDialogState.item?.id);
            setDeleteDialogState({ item: null, open: false });
          }}
        />
      </Modal>
    </PageLayout>
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
