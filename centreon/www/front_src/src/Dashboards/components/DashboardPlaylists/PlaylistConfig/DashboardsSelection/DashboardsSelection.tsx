import { InputPropsWithoutGroup } from '@centreon/ui';

const DashboardsSelection = ({
  fieldName
}: InputPropsWithoutGroup): JSX.Element => {
  return <p>{fieldName}</p>;
};

export default DashboardsSelection;
