import { FormProps } from '../../../Form';

export enum DashboardFormVariant {
  Create = 'create',
  Update = 'update'
}

export interface DashboardFormLabels {
  actions: {
    cancel: string;
    submit: {
      [DashboardFormVariant.Create]: string;
      [DashboardFormVariant.Update]: string;
    };
  };
  entity: {
    description: string;
    name: string;
  };
  title: {
    [DashboardFormVariant.Create]: string;
    [DashboardFormVariant.Update]: string;
  };
}

export interface DashboardFormDataShape {
  description?: string | null;
  name: string;
}

export interface DashboardFormProps {
  labels: DashboardFormLabels;
  onCancel?: () => void;
  onSubmit?: FormProps<DashboardFormDataShape>['submit'];
  resource?: DashboardFormDataShape;
  variant?: DashboardFormVariant;
}
