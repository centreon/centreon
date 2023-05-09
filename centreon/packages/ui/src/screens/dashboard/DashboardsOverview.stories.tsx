import { Meta } from '@storybook/react';
import { Header } from '../../components/Header';
import { Button } from '../../components/Button';
import { List } from '../../components/List';
import { Add as AddIcon } from '@mui/icons-material';


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


export const Default = {
  render: (args) => (
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
            >
              {args.actions.create.label}
            </Button>
          </div>
          <div className="content" style={{paddingBottom: '20px'}}>  {/* TODO DashboardsList.Content */}
            <List>
              {args.data.dashboards.map((dashboard) => (
                <List.Item
                  key={dashboard.id}
                  title={dashboard.name}
                  description={dashboard.description}
                />
              ))}
            </List>
          </div>
        </div>
      </div>
    </div>
  )
};