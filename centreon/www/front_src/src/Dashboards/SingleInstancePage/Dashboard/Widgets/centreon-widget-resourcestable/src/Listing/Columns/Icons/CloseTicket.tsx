import CloseIcon from '@mui/icons-material/Close';
import { Badge, SvgIcon } from '@mui/material';
import { useOpenTicketStyles } from '../Columns.styles';

const icon = (
  <g>
    <polygon points="12.13 10.8 10.81 12.11 11.8 13.1 13.12 11.79 12.13 10.8" />
    <polygon points="9.9 8.59 8.59 9.9 9.57 10.89 10.89 9.57 9.9 8.59" />
    <path d="M16.91,11.92c.87,0,1.67.24,2.38.63l1.92-1.9c.73-.72.87-1.75.33-2.29l-1.98-1.97c-.73.72-1.76.87-2.31.33-.55-.54-.4-1.57.33-2.29l-1.98-1.97c-.55-.54-1.58-.4-2.31.33L2.73,13.26c-.73.72-.87,1.74-.33,2.28h0s1.98,1.97,1.98,1.97c.73-.72,1.76-.87,2.31-.32.54.54.39,1.57-.34,2.29l1.98,1.97c.55.54,1.58.39,2.31-.33l1.89-1.88c-.39-.71-.63-1.51-.63-2.37,0-2.74,2.24-4.97,5-4.97ZM9.57,10.89l-.99-.98,1.32-1.31.99.98-1.32,1.31ZM10.81,12.11l1.32-1.31.99.98-1.32,1.31-.99-.98Z" />
  </g>
);

const CloseTicket = (): JSX.Element => {
  const { classes } = useOpenTicketStyles();
  return (
    <Badge
      overlap="circular"
      anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
      badgeContent={<CloseIcon color="error" sx={{ fontSize: '14px' }} />}
      classes={{ badge: classes.iconWithBadge }}
    >
      <SvgIcon color="primary">{icon}</SvgIcon>
    </Badge>
  );
};

export default CloseTicket;
