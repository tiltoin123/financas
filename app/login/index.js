const loga = async () => {
  const email = document.getElementById('email').value.trim()
  const senha = document.getElementById('senha').value.trim()

  if (!email || !senha) {
    alert('Preencha todos os campos')
    return
  }

  try {

    const response = await fetch('login.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        email: email,
        senha: senha
      })
    })

    const data = await response.text()

    console.log(data)

  } catch (error) {
    console.error(error)
    alert('Erro na requisição')
  }
}

document.querySelector('#logar').addEventListener('click', loga)