export default function TipCard({ tip }) {
  return (
    <div className="rounded-lg shadow-md p-4 mb-4 bg-white">
      <img
        src={tip.image}
        alt={tip.title}
        className="w-full h-32 object-cover rounded"
      />
      <h3 className="text-lg font-bold mt-2">{tip.title}</h3>
      <p className="text-gray-700">{tip.content}</p>
    </div>
  );
}
