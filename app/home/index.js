const painel = document.querySelector('#painel')
const modalEvento = document.querySelector('#modal-evento')

const camposDoEvento = ['#evento-nome', '#evento-descricao', '#evento-valor', '#evento-data']

let mesAtual = painel.dataset.mes // mês exibido (YYYY-MM)
let dados = null                  // última resposta de mes.php

// ---------- Formatação ----------

const moeda = (valor) => Number(valor).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' })

const dataBr = (iso) => iso.split('-').reverse().join('/')

const rotuloMes = (anoMes) => {
  const [ano, mes] = anoMes.split('-').map(Number)
  return new Date(ano, mes - 1, 1).toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' })
}

const somarMes = (anoMes, n) => {
  const [ano, mes] = anoMes.split('-').map(Number)
  const d = new Date(ano, mes - 1 + n, 1)
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
}

// ---------- Requisições ----------

// POST JSON; devolve o JSON da resposta ou null se a requisição falhar
const post = async (url, corpo) => {

  try {

    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(corpo)
    })

    return await response.json()

  } catch (error) {
    console.error(error)
    return null
  }
}

const carregar = async (mes) => {

  try {

    const response = await fetch(`./mes.php?mes=${encodeURIComponent(mes)}`)
    const data = await response.json()

    if (!data.success) {
      alert(data.message)
      return
    }

    mesAtual = mes
    dados = data.dados

    document.querySelector('#saldo-atual').textContent = moeda(data.saldoAtual)
    desenhar()

  } catch (error) {
    console.error(error)
    alert('Erro ao carregar o mês.')
  }
}

// ---------- Desenho da tela (textContent: nada do usuário vira HTML) ----------

const celula = (texto) => {
  const td = document.createElement('td')
  td.textContent = texto
  return td
}

const botao = (texto, aoClicar) => {
  const b = document.createElement('button')
  b.type = 'button'
  b.textContent = texto
  b.addEventListener('click', aoClicar)
  return b
}

const linha = (l) => {

  const tr = document.createElement('tr')
  tr.title = l.descricao ?? ''

  tr.append(
    celula(dataBr(l.data)),
    celula(l.nome),
    celula(l.categoria ?? 'Sem categoria'),
    celula(moeda(l.valor)),
    celula(l.realizado ? 'Efetivado' : 'Previsto')
  )

  const acoes = document.createElement('td')
  acoes.append(
    botao('Editar', () => editar(l)),
    botao('Apagar', () => apagar(l))
  )
  tr.append(acoes)

  return tr
}

const desenhar = () => {

  document.querySelector('#mes-titulo').textContent = rotuloMes(mesAtual)
  document.querySelector('#mes-anterior').disabled = mesAtual <= dados.minimo
  document.querySelector('#mes-proximo').disabled = mesAtual >= dados.maximo

  document.querySelector('#saldo-previsto').textContent = moeda(dados.saldoPrevisto)
  document.querySelector('#receitas').textContent = moeda(dados.receitas)
  document.querySelector('#despesas').textContent = moeda(Math.abs(dados.despesas))

  document.querySelector('#situacao-mes').textContent = dados.fechado ? 'Mês fechado' : 'Mês aberto'
  document.querySelector('#fechar-mes').hidden = dados.fechado

  document.querySelector('#lancamentos').replaceChildren(...dados.lancamentos.map(linha))

  document.querySelector('#lista-vazia').hidden = dados.lancamentos.length > 0
  document.querySelector('#tabela').hidden = dados.lancamentos.length === 0
}

// ---------- Ações por linha ----------
// Mês aberto: a linha é uma ocorrência da recorrência. Mês fechado: a linha é um evento.
// Os botões sempre aparecem; quem recusa é a classe, e a mensagem chega no alert.

const editar = (l) => {

  if (l.origem === 'recorrencia') {
    window.location.href = `../recorrencia/index.php?editar=${l.recorrenciaId}`
    return
  }

  abrirModalEvento(l)
}

const apagar = async (l) => {

  const recorrencia = l.origem === 'recorrencia'

  const pergunta = recorrencia
    ? `Remover "${l.nome}" de ${rotuloMes(mesAtual)} em diante? Os meses já fechados não mudam.`
    : `Apagar o lançamento "${l.nome}" deste mês fechado? Os saldos dos meses fechados serão refeitos.`

  if (!confirm(pergunta)) {
    return
  }

  const data = await post(
    recorrencia ? '../recorrencia/encerrar.php' : '../evento/excluir.php',
    recorrencia ? { id: l.recorrenciaId, mes: mesAtual } : { id: l.eventoId }
  )

  if (!data) {
    alert('Erro ao apagar.')
    return
  }

  if (!data.success) {
    alert(data.message)
    return
  }

  carregar(mesAtual)
}

// ---------- Modal do evento (mês fechado) ----------

const abrirModalEvento = (l) => {

  document.querySelector('#evento-id').value = l.eventoId
  document.querySelector('#evento-nome').value = l.nome
  document.querySelector('#evento-descricao').value = l.descricao ?? ''
  document.querySelector('#evento-valor').value = Math.abs(l.valor).toFixed(2)
  document.querySelector('#evento-categoria').value = l.categoriaId ?? ''
  document.querySelector('#evento-data').value = l.data

  document.querySelector(`input[name="evento-tipo"][value="${l.tipo}"]`).checked = true

  modalEvento.showModal()
}

const salvarEvento = async () => {

  // Validação nativa nos próprios campos; o servidor valida tudo de novo na classe
  if (!camposDoEvento.every((seletor) => document.querySelector(seletor).reportValidity())) {
    return
  }

  const data = await post('../evento/salva.php', {
    id: Number(document.querySelector('#evento-id').value) || 0,
    tipo: document.querySelector('input[name="evento-tipo"]:checked')?.value,
    nome: document.querySelector('#evento-nome').value,
    descricao: document.querySelector('#evento-descricao').value,
    valor: document.querySelector('#evento-valor').value,
    categoria_id: document.querySelector('#evento-categoria').value,
    data: document.querySelector('#evento-data').value
  })

  if (!data) {
    alert('Erro ao salvar.')
    return
  }

  if (!data.success) {
    alert(data.message)
    return
  }

  modalEvento.close()
  carregar(mesAtual)
}

// ---------- Fechar mês ----------

const fecharMes = async () => {

  const meses = dados.pendentes.map(rotuloMes).join(', ')

  if (!confirm(`Isto vai fechar: ${meses}. Continuar?`)) {
    return
  }

  const data = await post('./fechar.php', { mes: mesAtual })

  if (!data) {
    alert('Erro ao fechar o mês.')
    return
  }

  if (!data.success) {
    alert(data.message)
    return
  }

  carregar(mesAtual)
}

// ---------- Eventos ----------

document.querySelector('#mes-anterior').addEventListener('click', () => carregar(somarMes(mesAtual, -1)))
document.querySelector('#mes-proximo').addEventListener('click', () => carregar(somarMes(mesAtual, 1)))
document.querySelector('#mes-hoje').addEventListener('click', () => carregar(painel.dataset.mes))
document.querySelector('#fechar-mes').addEventListener('click', fecharMes)

document.querySelector('#evento-salvar').addEventListener('click', salvarEvento)
document.querySelector('#evento-cancelar').addEventListener('click', () => modalEvento.close())

carregar(mesAtual)