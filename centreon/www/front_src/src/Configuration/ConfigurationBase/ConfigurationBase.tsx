import { ConfigurationBase } from '../models';
import Page from './Page';

const Base = ({ columns, resourceType, Form }: ConfigurationBase) => {
  return <Page columns={columns} resourceType={resourceType} Form={Form} />;
};

export default Base;
