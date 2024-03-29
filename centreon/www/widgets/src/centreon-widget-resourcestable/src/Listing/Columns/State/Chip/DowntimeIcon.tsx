import parse from 'html-react-parser';

import { SvgIcon } from '@mui/material';

const icon =
  '<svg width="24" height="24" viewBox="0 0 24 24"><defs><style>.a{fill:none;}</style></defs><g transform="translate(-94.852 -137.365)"><rect class="a" width="24" height="24" transform="translate(94.852 137.365)"/><g transform="translate(97.108 140.129)"><path d="M103.476,143.64a1.692,1.692,0,1,0-1.692-1.692A1.692,1.692,0,0,0,103.476,143.64Z" transform="translate(-97.132 -140.255)"/><path d="M114.266,150.2a1.05,1.05,0,0,0-.968-.647s-2.067-.275-2.68.125a2.464,2.464,0,0,0-.644.8l-3.454-.547c-.265-.874-1.4-4.183-1.4-4.183l-.846-1.353a1.743,1.743,0,0,0-1.438-.846,4.571,4.571,0,0,0-.677.084l-4.4,1.867v3.976H98l2.222.352-1.61,8.107h1.777l1.522-6.768,1.777,1.692v5.076h1.692v-6.345l-1.19-1.135,5.428.859-3.084,6.815,10.976-.11Zm-10.206-2.611s.577,1.356.88,2.089l-1.259-.2Zm-4.606,1.22v-2.212l1.522-.592-.586,2.952Z" transform="translate(-97.762 -139.74)"/></g></g></svg>';

const Downtime = (): JSX.Element => {
  return (
    <SvgIcon data-icon="Downtime" fontSize="small" viewBox="0 0 60 60">
      {parse(icon)}
    </SvgIcon>
  );
};

export default Downtime;
