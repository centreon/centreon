export const TicketLink = ({ row }) => {
  return (
    <a
      onClick={(e): void => {
        e.stopPropagation();
      }}
      href={row.extra?.open_tickets?.tickets?.link}
      target="_blank"
      rel="noreferrer"
    >
      {row.extra?.open_tickets?.tickets?.id}
    </a>
  );
};
