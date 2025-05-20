import { ReactElement } from 'react';
import { placeholder } from './Placeholder.module.css';

const Placeholder = (): ReactElement => (
  <div className={placeholder}>
    <div className="w-2 h-2 bg-primary-main dark:bg-black" />
    <div className="w-3 h-3 bg-error-main dark:bg-primary-main" />
  </div>
);

export default Placeholder;
