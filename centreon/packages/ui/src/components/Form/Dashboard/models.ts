import { FormProps } from 'src/Form';

export interface DashboardFormLabels {
  actions: {
    cancel: string;
    submit: {
      create: string;
      update: string;
    };
  };
  entity: {
    description: string;
    name: string;
  };
  title: {
    create: string;
    update: string;
  };
}

export interface DashboardFormDataShape {
  description?: string;
  name: string;
}

export interface DashboardFormProps {
  labels: DashboardFormLabels;
  onCancel?: () => void;
  onSubmit?: FormProps<DashboardFormDataShape>['submit'];
  resource?: DashboardFormDataShape;
  variant?: 'create' | 'update';
}
