import Webpage from './WebPage';
import type { WebPageProps } from './models';

const Widget = (props: WebPageProps): JSX.Element => <Webpage {...props} />;

export default Widget;
