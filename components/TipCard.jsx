export default function TipCard({ tip }) {
  return (
    <div className="glass-card">
      <div className="card-image mb-3">
        <img
          src={tip.image}
          alt={tip.title}
          className="w-full h-40 object-cover"
        />
      </div>
      <h3 className="text-lg font-semibold">{tip.title}</h3>
      <p className="card-text mt-1 leading-relaxed">{tip.content}</p>
    </div>
  );
}
