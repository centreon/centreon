import { ConfigurationBase } from '../models';
import Page from './Page';

const Base = ({
  columns,
  resourceType,
  form
}: ConfigurationBase): JSX.Element => {
  return <Page columns={columns} resourceType={resourceType} form={form} />;
};

export default Base;
