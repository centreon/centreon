import type { WebPageProps } from './models';
import Webpage from './WebPage';

const Widget = (props: WebPageProps): JSX.Element => <Webpage {...props} />;

export default Widget;
