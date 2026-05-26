interface SalonHomeProps {
  params: Promise<{ slug: string }>
}

export default async function SalonHomePage({ params }: SalonHomeProps) {
  const { slug } = await params

  return (
    <div className="flex flex-1 flex-col items-center justify-center px-6 py-24 text-center">
      <div className="mx-auto max-w-xl">
        <h1 className="text-3xl font-bold text-gray-900">
          Bem-vindo ao{' '}
          <span className="text-indigo-600 capitalize">{slug}</span>
        </h1>
        <p className="mt-4 text-base text-gray-600">
          Escolha um serviço e agende seu horário com facilidade.
        </p>
        <a
          href={`/${slug}/booking`}
          className="mt-8 inline-block rounded-full bg-indigo-600 px-8 py-3 text-sm font-semibold text-white shadow hover:bg-indigo-500 transition-colors"
        >
          Agendar
        </a>
      </div>
    </div>
  )
}
