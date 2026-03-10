export const TicketLink = ({ row }) => {
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
