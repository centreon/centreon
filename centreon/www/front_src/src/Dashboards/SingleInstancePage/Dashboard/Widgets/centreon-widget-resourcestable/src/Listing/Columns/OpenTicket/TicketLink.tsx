interface TicketLinkProps {
  row: {
    extra?: {
      open_tickets?: { tickets?: { id?: string | number; link?: string } };
    };
  };
}

export const TicketLink = ({ row }: TicketLinkProps) => {
  return (
    <a
      href={row.extra?.open_tickets?.tickets?.link}
      onClick={(e): void => {
        e.stopPropagation();
      }}
      rel="noreferrer"
      target="_blank"
    >
      {row.extra?.open_tickets?.tickets?.id}
    </a>
  );
};
