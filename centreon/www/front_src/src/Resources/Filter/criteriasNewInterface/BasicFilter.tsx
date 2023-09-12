import Divider from '@mui/material/Divider';

import { CheckBoxWrapper as StateCheckBox } from './CheckBox';
import PeriodTime from './PeriodTime';
import BasicSection from './BasicSection';

const BasicFilter = (data): JSX.Element => {
  const { resourcesType } = data;

  return (
    <div>
      {resourcesType.map((resourceType) => (
        <>
          <BasicSection data={{ ...data, resourceType }} />
          <Divider />
        </>
      ))}

      <StateCheckBox direction="horizontal" options={data?.state} />
      <PeriodTime />
    </div>
  );
};

export default BasicFilter;
