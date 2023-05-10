import { Meta } from '@storybook/react';
import { Header } from '../../components/Header';
import { Button } from '../../components/Button';
import { List } from '../../components/List';
import { Add as AddIcon } from '@mui/icons-material';
import { atom, useAtom } from 'jotai';
import { DashboardForm, DashboardFormProps } from '../../components/Form/Dashboard';
import { Default as DashboardFormDefaultStory } from '../../components/Form/Dashboard/DashboardForm.stories';
import { Dialog } from '../../components/Dialog';
import { useEffect } from 'react';


const meta: Meta = {
  title: 'screens/Dashboard/Dashboards overview',
  args: {
    title: 'Dashboards overview',
    actions: {
      create: {
        label: 'Create a dashboard'
      }
    },
    data: {
      dashboards: [
        {id: 1, name: 'Dashboard 1', description: 'Dashboard 1 description'},
        {id: 2, name: 'Dashboard 2', description: 'Dashboard 2 description'},
        {id: 3, name: 'Dashboard 3', description: 'Dashboard 3 description'},
        {id: 4, name: 'Dashboard 4', description: 'Dashboard 4 description'},
        {id: 5, name: 'Dashboard 5', description: 'Dashboard 5 description'}
      ]
    }
  }
};

export default meta;


const dialogStateAtom = atom<{ open: boolean, variant: DashboardFormProps['variant'] }>({
  open: false,
  variant: 'create'
});

const dataDashboardsAtom = atom<Array<{id: number, name: string, description: string}>>([]);

const DefaultView = (args) => {

  const [dialogState, setDialogState] = useAtom(dialogStateAtom);
  const [dataDashboards, setDataDashboards] = useAtom(dataDashboardsAtom);

  useEffect(() => {
    setDataDashboards(args.data.dashboards);
  }, [args.data.dashboards]);

  const createDashboard = (data) => {
    setDataDashboards((prev) => [...prev, data].sort((a, b) => a.name.localeCompare(b.name)));
    setDialogState({open: false, variant: 'create'});
  }

  const updateDashboard = (data) => {
    setDataDashboards((prev) => prev.map((dashboard) => dashboard.id === data.id ? data : dashboard));
    setDialogState({open: false, variant: 'update'});
  }

  const deleteDashboard = (id) => {
    setDataDashboards((prev) => prev.filter((dashboard) => dashboard.id !== id));
  }

  return (
    <div>
      <Header
        title={args.title}
      />
      <div className="dashboards-list"> {/* TODO DashboardsListLayout */}
        <div className="dashboard-list-header">  {/* TODO DashboardsList */}
          <div className="actions" style={{paddingBottom: '20px'}}>  {/* TODO DashboardsList.Actions */}
            <Button
              variant="primary"
              iconVariant="start"
              icon={<AddIcon/>}
              onClick={() => setDialogState({open: true, variant: 'create'})}
            >
              {args.actions.create.label}
            </Button>
          </div>
          <div className="content" style={{paddingBottom: '20px'}}>  {/* TODO DashboardsList.Content */}
            <List>
              {dataDashboards.map((dashboard) => (
                <List.Item
                  key={dashboard.id}
                  title={dashboard.name}
                  description={dashboard.description}
                />
              ))}
            </List>
          </div>
          <Dialog
            open={dialogState.open}
            onClose={() => setDialogState({open: false, variant: 'create'})}
          >
            <DashboardForm
              variant={dialogState.variant}
              labels={DashboardFormDefaultStory!.args!.labels!}
              onSubmit={(values) => {
                dialogState.variant === 'create' ? createDashboard(values) : updateDashboard(values);
              }}
              onCancel={() => setDialogState({open: false, variant: 'create'})}
            />
          </Dialog>
        </div>
      </div>
    </div>
  );
};

export const Default = {
  render: DefaultView
};