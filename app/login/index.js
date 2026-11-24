const loga = async () => {

  const email = document.querySelector('#email')?.value
  const senha = document.querySelector('#senha')?.value

  if (!email || !senha) {
    alert('Preencha todos os campos')
    return
  }

  try {

    const response = await fetch('./login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
    'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]').content
    },
      body: JSON.stringify({
        email,
        senha
      })
    })

    const data = await response.json()

    if (data.success) {
      window.location.href = '../home/index.php'
      return
    }

    alert(data.message)

  } catch (error) {
    console.error(error)
    alert('Erro ao logar.')
  }
}

document.querySelector('#logar').addEventListener('click', loga)